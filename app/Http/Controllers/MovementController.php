<?php

namespace App\Http\Controllers;

use App\Enums\AccountabilityCategory;
use App\Enums\MovementStatus;
use App\Models\AuditLog;
use App\Models\Personnel;
use App\Models\PersonnelMovement;
use App\Models\Unit;
use App\Services\MovementService;
use App\Support\Csv;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MovementController extends Controller
{
    public function __construct(protected MovementService $service) {}

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'due');
        $grace = Settings::int('overdue_grace_minutes', 0);
        $unit = $request->filled('unit') ? Unit::query()->find($request->integer('unit')) : null;

        $base = PersonnelMovement::query()
            ->with(['personnel.rank', 'application.type', 'dutyAssignment', 'departureRecorder', 'returnRecorder'])
            ->when($unit, fn ($q) => $q->whereHas('personnel', fn ($p) => $p->assignedOn(now(), $unit->descendantIds())))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.trim($request->input('q')).'%';
                $q->whereHas('personnel', fn ($p) => $p->where('last_name', 'like', $term)->orWhere('first_name', 'like', $term)->orWhere('service_number', 'like', $term));
            });

        $counts = [
            'due' => (clone $base)->where('status', MovementStatus::Scheduled->value)->where('expected_departure_at', '<=', now()->endOfDay()->addDay())->count(),
            'away' => (clone $base)->where('status', MovementStatus::Departed->value)->count(),
            'overdue' => (clone $base)->where('status', MovementStatus::Departed->value)->where('expected_return_at', '<', now()->subMinutes($grace))->count(),
            'returning' => (clone $base)->where('status', MovementStatus::Departed->value)->whereBetween('expected_return_at', [now()->startOfDay(), now()->endOfDay()])->count(),
        ];

        $query = match ($tab) {
            'due' => (clone $base)->where('status', MovementStatus::Scheduled->value)->where('expected_departure_at', '<=', now()->endOfDay()->addDay())->orderBy('expected_departure_at'),
            'away' => (clone $base)->where('status', MovementStatus::Departed->value)->orderBy('expected_return_at'),
            'overdue' => (clone $base)->where('status', MovementStatus::Departed->value)->where('expected_return_at', '<', now()->subMinutes($grace))->orderBy('expected_return_at'),
            'returning' => (clone $base)->where('status', MovementStatus::Departed->value)->whereBetween('expected_return_at', [now()->startOfDay(), now()->endOfDay()])->orderBy('expected_return_at'),
            default => (clone $base)
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
                ->when($request->filled('from'), fn ($q) => $q->where('expected_return_at', '>=', $request->date('from')->startOfDay()))
                ->when($request->filled('to'), fn ($q) => $q->where('expected_departure_at', '<=', $request->date('to')->endOfDay()))
                ->latest('expected_departure_at'),
        };

        if ($request->input('format') === 'csv') {
            return Csv::download('movements-'.$tab.'-'.now()->format('Ymd-His').'.csv',
                ['Service No.', 'Rank', 'Name', 'Category', 'Source', 'Reference', 'Planned departure', 'Actual departure', 'Planned return', 'Actual return', 'Status'],
                $query->get()->map(fn (PersonnelMovement $m) => [
                    $m->personnel->service_number, $m->personnel->rank?->abbreviation, $m->personnel->full_name, $m->category->label(), $m->sourceLabel(), $m->sourceReference(),
                    $m->expected_departure_at->format('Y-m-d H:i'), $m->actual_departure_at?->format('Y-m-d H:i'), $m->expected_return_at->format('Y-m-d H:i'),
                    $m->actual_return_at?->format('Y-m-d H:i'), $m->isOverdue() ? 'Overdue' : $m->status->shortLabel(),
                ]));
        }

        return view('movements.index', [
            'movements' => $query->paginate(25)->withQueryString(),
            'tab' => $tab,
            'counts' => $counts,
            'units' => Unit::options(),
            'filters' => $request->only(['q', 'unit', 'status', 'category', 'from', 'to']),
        ]);
    }

    public function show(PersonnelMovement $movement): View
    {
        $movement->load(['personnel.rank', 'application.type', 'dutyAssignment', 'departureRecorder', 'returnRecorder', 'creator', 'verificationCases']);

        return view('movements.show', [
            'm' => $movement,
            'history' => AuditLog::query()->where('entity_type', 'PersonnelMovement')->where('entity_id', $movement->id)->with('user')->latest('created_at')->get(),
            'canSeeSensitive' => request()->user()->hasPermission('medical.view'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('movements.create', [
            'personnel' => Personnel::query()->assignedOn(now())->where('service_status', 'active')->with('rank')->ordered()->get(),
            'selected' => $request->integer('personnel'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'personnel_id' => ['required', 'exists:personnel,id'],
            'category' => ['required', Rule::in(array_map(fn ($c) => $c->value, AccountabilityCategory::awayCategories()))],
            'authority_reference' => ['required', 'string', 'max:150'],
            'destination' => ['nullable', 'string', 'max:200'],
            'expected_departure_at' => ['required', 'date'],
            'expected_return_at' => ['required', 'date', 'after:expected_departure_at'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'depart_now' => ['nullable', 'boolean'],
        ]);
        $movement = $this->service->createDirect($request->user(), Personnel::query()->findOrFail($data['personnel_id']), $data);

        return redirect()->route('movements.show', $movement)->with('success', 'Authorized movement recorded.');
    }

    public function depart(Request $request, PersonnelMovement $movement): RedirectResponse
    {
        $data = $request->validate(['actual_at' => ['required', 'date'], 'remarks' => ['nullable', 'string', 'max:255']]);
        $this->service->recordDeparture($request->user(), $movement, Carbon::parse($data['actual_at']), $data['remarks'] ?? null);

        return back()->with('success', 'Departure of '.$movement->personnel->rank_name.' recorded at '.Carbon::parse($data['actual_at'])->format('d M Y H:i').'.');
    }

    public function return(Request $request, PersonnelMovement $movement): RedirectResponse
    {
        $data = $request->validate(['actual_at' => ['required', 'date'], 'remarks' => ['nullable', 'string', 'max:255']]);
        $this->service->recordReturn($request->user(), $movement, Carbon::parse($data['actual_at']), $data['remarks'] ?? null);

        return back()->with('success', 'Return of '.$movement->personnel->rank_name.' recorded. Member is now accounted for as present.');
    }

    public function extend(Request $request, PersonnelMovement $movement): RedirectResponse
    {
        $data = $request->validate(['expected_return_at' => ['required', 'date'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->service->extend($request->user(), $movement, Carbon::parse($data['expected_return_at']), $data['reason']);

        return back()->with('success', 'Expected return updated. The original expected return is preserved.');
    }

    public function correct(Request $request, PersonnelMovement $movement): RedirectResponse
    {
        $data = $request->validate([
            'actual_departure_at' => ['nullable', 'date'],
            'actual_return_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $this->service->correct($request->user(), $movement, $data, $data['reason']);

        return back()->with('success', 'Correction recorded with old and new values in the audit trail.');
    }

    public function cancel(Request $request, PersonnelMovement $movement): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->service->cancel($movement, $request->user(), $data['reason']);

        return back()->with('success', 'Scheduled movement cancelled.');
    }
}
