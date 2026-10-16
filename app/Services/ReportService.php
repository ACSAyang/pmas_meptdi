<?php

namespace App\Services;

use App\Enums\AccountabilityCategory as Cat;
use App\Enums\ApplicationStatus;
use App\Enums\MovementStatus;
use App\Models\DutyAssignment;
use App\Models\PersonnelApplication;
use App\Models\PersonnelMovement;
use App\Models\Unit;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Builds tabular operational reports. Calculations come from the same services
 * used by the dashboard so figures are consistent everywhere.
 */
class ReportService
{
    public const CATALOG = [
        'strength' => ['Daily Personnel Strength & Accountability', 'Assigned strength reconciled into mutually exclusive categories at a reporting time, with unit breakdown and exceptions.', 'shield'],
        'unresolved' => ['Unverified / Unaccounted Personnel', 'Members whose status is overdue, unverified or reported absent at a reporting time.', 'alert'],
        'overdue' => ['Overdue Return Report', 'Open movements past the expected return (plus grace period).', 'clock'],
        'schedule' => ['Scheduled Departures & Returns', 'Planned departures and returns within a date range, with actual times when recorded.', 'calendar'],
        'register' => ['Pass & Leave Application Register', 'All applications within a period with type, status and decision stage.', 'file'],
        'pending' => ['Pending Approval Report', 'Applications awaiting a decision, by stage and age.', 'inbox'],
        'training' => ['Training & Official Duty Summary', 'Training, schooling, official business and TDY assignments in a period.', 'graduation'],
        'movements' => ['Personnel Movement History', 'Actual departures and returns recorded in a period.', 'move'],
        'compliance' => ['Application & Document Compliance', 'Active applications with missing or unverified required documents.', 'paperclip'],
    ];

    public function __construct(protected AccountabilityService $accountability) {}

    /**
     * @return array{title:string, subtitle:string, columns:array, rows:array, params:array, summary:array}
     */
    public function build(string $type, Request $request): array
    {
        abort_unless(array_key_exists($type, self::CATALOG), 404);
        $canMed = $request->user()->hasPermission('medical.view');
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfDay();
        $unit = $request->filled('unit') ? Unit::query()->find($request->integer('unit')) : null;
        $unitIds = $unit?->descendantIds();
        $period = $from->format('d M Y').' – '.$to->format('d M Y');
        $inUnit = fn ($q) => $unitIds ? $q->whereHas('personnel', fn ($p) => $p->assignedOn(now(), $unitIds)) : $q;
        $type_label = fn (?PersonnelApplication $a) => $a && $a->type->is_sensitive && ! $canMed ? 'Restricted' : $a?->type?->name;

        $result = ['title' => self::CATALOG[$type][0], 'params' => ['unit' => $unit?->name ?? 'All units'], 'summary' => []];

        switch ($type) {
            case 'unresolved':
                $asOf = $request->filled('as_of') ? Carbon::parse($request->input('as_of')) : now();
                $snap = $this->accountability->snapshot($asOf, $unitIds);
                $rows = $snap->exceptions();
                $result += [
                    'subtitle' => 'As of '.$asOf->format('d M Y H:i'),
                    'columns' => ['Service No.', 'Rank', 'Name', 'Unit', 'Status', 'Basis / details', 'Since'],
                    'rows' => $rows->map(fn ($r) => [$r->personnel->service_number, $r->personnel->rank?->abbreviation, $r->personnel->full_name, $r->unit?->code, $r->category->label(), $r->publicDetail($canMed), $r->since?->format('d M Y H:i') ?? '—'])->all(),
                ];
                $result['params']['as_of'] = $asOf->format('d M Y H:i');
                $result['summary'] = ['Overdue' => $snap->count(Cat::Overdue), 'Unverified' => $snap->count(Cat::Unverified), 'Unaccounted' => $snap->count(Cat::Unaccounted), 'Assigned strength' => $snap->assigned()];
                break;

            case 'overdue':
                $grace = Settings::int('overdue_grace_minutes', 0);
                $rows = $inUnit(PersonnelMovement::query())->where('status', MovementStatus::Departed->value)
                    ->where('expected_return_at', '<', now()->subMinutes($grace))
                    ->with(['personnel.rank', 'application.type', 'dutyAssignment', 'verificationCases'])->orderBy('expected_return_at')->get();
                $result += [
                    'subtitle' => 'As of '.now()->format('d M Y H:i').' · grace period '.$grace.' min',
                    'columns' => ['Service No.', 'Rank', 'Name', 'Authority', 'Departed', 'Expected return', 'Overdue by', 'Case'],
                    'rows' => $rows->map(fn ($m) => [$m->personnel->service_number, $m->personnel->rank?->abbreviation, $m->personnel->full_name,
                        ($type_label($m->application) ?? $m->sourceLabel()).' '.$m->sourceReference(), $m->actual_departure_at->format('d M Y H:i'),
                        $m->expected_return_at->format('d M Y H:i'), $m->expected_return_at->diffForHumans(null, true),
                        ($c = $m->verificationCases->firstWhere('status', 'open')) ? '#'.$c->id.' open' : '—'])->all(),
                ];
                $result['summary'] = ['Overdue movements' => $rows->count()];
                break;

            case 'schedule':
                $rows = $inUnit(PersonnelMovement::query())->where('status', '!=', MovementStatus::Cancelled->value)
                    ->where(fn ($q) => $q->whereBetween('expected_departure_at', [$from, $to])->orWhereBetween('expected_return_at', [$from, $to]))
                    ->with(['personnel.rank', 'application.type', 'dutyAssignment'])->orderBy('expected_departure_at')->get();
                $result += [
                    'subtitle' => $period,
                    'columns' => ['Service No.', 'Rank', 'Name', 'Category', 'Planned departure', 'Actual departure', 'Planned return', 'Actual return', 'Status'],
                    'rows' => $rows->map(fn ($m) => [$m->personnel->service_number, $m->personnel->rank?->abbreviation, $m->personnel->full_name,
                        $m->application?->type?->is_sensitive && ! $canMed ? 'Restricted' : $m->category->label(),
                        $m->expected_departure_at->format('d M Y H:i'), $m->actual_departure_at?->format('d M Y H:i') ?? '—',
                        $m->expected_return_at->format('d M Y H:i'), $m->actual_return_at?->format('d M Y H:i') ?? '—',
                        $m->isOverdue() ? 'Overdue' : $m->status->shortLabel()])->all(),
                ];
                $result['params']['period'] = $period;
                $result['summary'] = ['Movements' => $rows->count(), 'Departed' => $rows->whereNotNull('actual_departure_at')->count(), 'Returned' => $rows->whereNotNull('actual_return_at')->count()];
                break;

            case 'register':
                $rows = $inUnit(PersonnelApplication::query())->where('status', '!=', ApplicationStatus::Draft->value)
                    ->where('start_at', '<=', $to)->where('end_at', '>=', $from)
                    ->when($request->filled('type'), fn ($q) => $q->where('application_type_id', $request->integer('type')))
                    ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                    ->with(['personnel.rank', 'type', 'currentStep'])->orderBy('start_at')->get();
                $result += [
                    'subtitle' => $period,
                    'columns' => ['Reference', 'Service No.', 'Rank', 'Name', 'Type', 'From', 'To', 'Days', 'Status', 'Submitted', 'Late'],
                    'rows' => $rows->map(fn ($a) => [$a->reference_number, $a->personnel->service_number, $a->personnel->rank?->abbreviation, $a->personnel->full_name,
                        $type_label($a), $a->start_at->format('d M Y H:i'), $a->end_at->format('d M Y H:i'), $a->durationDays(), $a->status->label(),
                        $a->submitted_at?->format('d M Y'), $a->is_late || $a->is_retrospective ? 'Yes' : ''])->all(),
                ];
                $result['params']['period'] = $period;
                $result['summary'] = collect(ApplicationStatus::cases())->mapWithKeys(fn ($s) => [$s->label() => $rows->where('status', $s)->count()])->filter()->all();
                break;

            case 'pending':
                $rows = $inUnit(PersonnelApplication::query())->where('status', ApplicationStatus::Pending->value)
                    ->with(['personnel.rank', 'type', 'currentStep.role'])->orderBy('submitted_at')->get();
                $result += [
                    'subtitle' => 'As of '.now()->format('d M Y H:i'),
                    'columns' => ['Reference', 'Rank', 'Name', 'Type', 'Period', 'Current stage', 'Awaiting role', 'Submitted', 'Waiting', 'Docs'],
                    'rows' => $rows->map(fn ($a) => [$a->reference_number, $a->personnel->rank?->abbreviation, $a->personnel->full_name, $type_label($a),
                        $a->start_at->format('d M').' – '.$a->end_at->format('d M Y'), $a->currentStep?->name, $a->currentStep?->role?->name,
                        $a->submitted_at?->format('d M Y H:i'), $a->submitted_at?->diffForHumans(null, true),
                        $a->unverifiedRequiredDocuments() ? 'Incomplete' : 'Complete'])->all(),
                ];
                $result['summary'] = $rows->groupBy(fn ($a) => $a->currentStep?->name ?? '—')->map->count()->all();
                break;

            case 'training':
                $rows = DutyAssignment::query()->where('status', '!=', 'cancelled')
                    ->where('start_at', '<=', $to)->where('end_at', '>=', $from)
                    ->when($unitIds, fn ($q) => $q->whereHas('personnel', fn ($p) => $p->assignedOn(now(), $unitIds)))
                    ->with(['personnel.rank', 'movement'])->orderBy('start_at')->get();
                $result += [
                    'subtitle' => $period,
                    'columns' => ['Rank', 'Name', 'Type', 'Activity', 'Location', 'Start', 'End', 'Reference', 'Away', 'Status'],
                    'rows' => $rows->map(fn ($d) => [$d->personnel->rank?->abbreviation, $d->personnel->full_name, $d->assignment_type->label(), $d->title, $d->location,
                        $d->start_at->format('d M Y'), $d->end_at->format('d M Y'), $d->reference_number, $d->is_away ? 'Yes' : 'No', DutyAssignment::STATUSES[$d->status]])->all(),
                ];
                $result['params']['period'] = $period;
                $result['summary'] = $rows->groupBy(fn ($d) => $d->assignment_type->label())->map->count()->all();
                break;

            case 'movements':
                $rows = $inUnit(PersonnelMovement::query())->whereNotNull('actual_departure_at')
                    ->where(fn ($q) => $q->whereBetween('actual_departure_at', [$from, $to])->orWhereBetween('actual_return_at', [$from, $to]))
                    ->with(['personnel.rank', 'application.type', 'dutyAssignment', 'departureRecorder', 'returnRecorder'])->orderBy('actual_departure_at')->get();
                $result += [
                    'subtitle' => $period,
                    'columns' => ['Rank', 'Name', 'Category', 'Authority', 'Departed', 'Recorded by', 'Returned', 'Recorded by', 'Late return'],
                    'rows' => $rows->map(fn ($m) => [$m->personnel->rank?->abbreviation, $m->personnel->full_name,
                        $m->application?->type?->is_sensitive && ! $canMed ? 'Restricted' : $m->category->label(), $m->sourceReference(),
                        $m->actual_departure_at->format('d M Y H:i'), $m->departureRecorder?->name, $m->actual_return_at?->format('d M Y H:i') ?? 'Still away',
                        $m->returnRecorder?->name, $m->wasReturnedLate() ? 'Yes' : ($m->isOverdue() ? 'Overdue' : '')])->all(),
                ];
                $result['params']['period'] = $period;
                $result['summary'] = ['Departures' => $rows->count(), 'Returns' => $rows->whereNotNull('actual_return_at')->count(), 'Late returns' => $rows->filter->wasReturnedLate()->count()];
                break;

            case 'compliance':
                $rows = $inUnit(PersonnelApplication::query())->whereIn('status', [ApplicationStatus::Pending->value, ApplicationStatus::Returned->value, ApplicationStatus::Approved->value])
                    ->with(['personnel.rank', 'type.documentRequirements', 'documents', 'currentStep'])->orderBy('start_at')->get()
                    ->map(fn ($a) => ['a' => $a, 'missing' => $a->missingRequiredDocuments(), 'unverified' => array_diff($a->unverifiedRequiredDocuments(), $a->missingRequiredDocuments())])
                    ->filter(fn ($x) => $x['missing'] || $x['unverified']);
                $result += [
                    'subtitle' => 'As of '.now()->format('d M Y H:i'),
                    'columns' => ['Reference', 'Rank', 'Name', 'Type', 'Status', 'Missing / rejected', 'Awaiting verification'],
                    'rows' => $rows->map(fn ($x) => [$x['a']->reference_number, $x['a']->personnel->rank?->abbreviation, $x['a']->personnel->full_name, $type_label($x['a']),
                        $x['a']->status->label().($x['a']->currentStep ? ' – '.$x['a']->currentStep->name : ''),
                        implode('; ', $x['missing']) ?: '—', implode('; ', $x['unverified']) ?: '—'])->values()->all(),
                ];
                $result['summary'] = ['Applications with gaps' => $rows->count()];
                break;
        }

        return $result;
    }
}
