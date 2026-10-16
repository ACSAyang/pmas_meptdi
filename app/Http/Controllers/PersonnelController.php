<?php

namespace App\Http\Controllers;

use App\Http\Requests\PersonnelRequest;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Models\Rank;
use App\Models\Unit;
use App\Services\AccountabilityService;
use App\Services\AuditLogger;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonnelController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->filtered($request);

        return view('personnel.index', [
            'personnel' => $query->with(['rank', 'currentAssignment.unit', 'user'])->paginate(25)->withQueryString(),
            'units' => Unit::options(),
            'ranks' => Rank::query()->orderBy('sort_order')->get(),
            'filters' => $request->only(['q', 'unit', 'rank', 'status']),
            'totals' => [
                'active' => Personnel::query()->where('service_status', 'active')->count(),
                'assigned' => Personnel::query()->assignedOn(now())->count(),
                'officers' => Personnel::query()->assignedOn(now())->whereHas('rank', fn ($q) => $q->where('category', 'officer'))->count(),
            ],
        ]);
    }

    protected function filtered(Request $request)
    {
        $status = $request->input('status', 'active');

        return Personnel::query()
            ->search($request->input('q'))
            ->when($request->filled('rank'), fn ($q) => $q->where('personnel.rank_id', $request->integer('rank')))
            ->when($status !== 'all', fn ($q) => $q->where('service_status', $status))
            ->when($request->filled('unit'), function ($q) use ($request) {
                $unit = Unit::query()->find($request->integer('unit'));
                $q->assignedOn(now(), $unit ? $unit->descendantIds() : [0]);
            })
            ->ordered();
    }

    public function export(Request $request): StreamedResponse
    {
        $canSensitive = $request->user()->hasPermission('personnel.view_sensitive');
        $rows = $this->filtered($request)->with(['rank', 'currentAssignment.unit'])->get();
        app(AuditLogger::class)->log('personnel.exported', null, null, ['count' => $rows->count(), 'filters' => $request->only(['q', 'unit', 'rank', 'status'])], 'Masterlist exported to CSV');

        $headers = ['Service No.', 'Rank', 'Last Name', 'First Name', 'Middle Name', 'Suffix', 'Sex', 'Unit Code', 'Unit', 'Assignment Type', 'Designation', 'Service Status', 'E-mail'];
        if ($canSensitive) {
            array_push($headers, 'Contact No.', 'Emergency Contact', 'Emergency No.');
        }

        return Csv::download('personnel-masterlist-'.now()->format('Ymd-His').'.csv', $headers, $rows->map(function (Personnel $p) use ($canSensitive) {
            $row = [
                $p->service_number, $p->rank?->abbreviation, $p->last_name, $p->first_name, $p->middle_name, $p->suffix, $p->sex,
                $p->currentAssignment?->unit?->code, $p->currentAssignment?->unit?->name, $p->currentAssignment?->assignment_type,
                $p->designation, Personnel::STATUSES[$p->service_status] ?? $p->service_status, $p->email,
            ];
            if ($canSensitive) {
                array_push($row, $p->contact_number, $p->emergency_contact_name, $p->emergency_contact_number);
            }

            return $row;
        }));
    }

    public function show(Request $request, Personnel $personnel, AccountabilityService $accountability): View
    {
        Gate::authorize('view', $personnel);
        $user = $request->user();
        $personnel->load(['rank', 'assignments.unit', 'assignments.creator', 'user.roles']);
        $tab = $request->input('tab', 'overview');

        $canSensitiveMedical = $user->hasPermission('medical.view') || $user->personnel_id === $personnel->id;

        return view('personnel.show', [
            'p' => $personnel,
            'tab' => $tab,
            'status' => $accountability->statusOf($personnel),
            'canSensitive' => Gate::allows('viewSensitive', $personnel),
            'canSensitiveMedical' => $canSensitiveMedical,
            'applications' => $personnel->applications()->with(['type', 'currentStep'])->latest('start_at')->paginate(10, ['*'], 'apage')->withQueryString(),
            'movements' => $personnel->movements()->with(['application.type', 'dutyAssignment', 'departureRecorder', 'returnRecorder'])->latest('expected_departure_at')->paginate(10, ['*'], 'mpage')->withQueryString(),
            'events' => $personnel->accountabilityEvents()->with(['recorder', 'voider'])->latest('occurred_at')->paginate(15, ['*'], 'epage')->withQueryString(),
            'duties' => $personnel->dutyAssignments()->latest('start_at')->get(),
            'units' => Unit::options(),
        ]);
    }

    public function create(): View
    {
        return view('personnel.form', [
            'p' => new Personnel(['service_status' => 'active']),
            'ranks' => Rank::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'units' => Unit::options(),
        ]);
    }

    public function store(PersonnelRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $personnel = DB::transaction(function () use ($data, $request) {
            $personnel = Personnel::query()->create(collect($data)->except(['unit_id', 'assignment_type', 'start_date', 'order_reference'])->all());
            PersonnelAssignment::query()->create([
                'personnel_id' => $personnel->id,
                'unit_id' => $data['unit_id'],
                'assignment_type' => $data['assignment_type'],
                'start_date' => $data['start_date'],
                'order_reference' => $data['order_reference'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            return $personnel;
        });

        return redirect()->route('personnel.show', $personnel)->with('success', $personnel->rank_name.' was added to the masterlist.');
    }

    public function edit(Personnel $personnel): View
    {
        return view('personnel.form', [
            'p' => $personnel,
            'ranks' => Rank::query()->orderBy('sort_order')->get(),
            'units' => Unit::options(),
        ]);
    }

    public function update(PersonnelRequest $request, Personnel $personnel): RedirectResponse
    {
        $personnel->update(collect($request->validated())->except(['unit_id', 'assignment_type', 'start_date', 'order_reference'])->all());

        return redirect()->route('personnel.show', $personnel)->with('success', 'Personnel record updated.');
    }

    /**
     * Ends the current assignment and (optionally) starts a new one. History is preserved.
     */
    public function reassign(Request $request, Personnel $personnel): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['reassign', 'end'])],
            'effective_date' => ['required', 'date'],
            'unit_id' => ['required_if:action,reassign', 'nullable', 'exists:units,id'],
            'assignment_type' => ['required_if:action,reassign', 'nullable', Rule::in(array_keys(PersonnelAssignment::TYPES))],
            'order_reference' => ['nullable', 'string', 'max:100'],
            'end_reason' => ['required', 'string', 'max:150'],
            'new_status' => ['nullable', Rule::in(array_keys(Personnel::STATUSES))],
        ]);

        DB::transaction(function () use ($data, $personnel, $request) {
            $effective = \Illuminate\Support\Carbon::parse($data['effective_date']);
            $current = $personnel->assignments()->whereNull('end_date')->get();
            foreach ($current as $a) {
                if ($a->start_date->gte($effective)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['effective_date' => 'The effective date must be after the current assignment start ('.$a->start_date->format('d M Y').').']);
                }
                $a->update(['end_date' => $data['action'] === 'reassign' ? $effective->copy()->subDay() : $effective, 'end_reason' => $data['end_reason']]);
            }
            if ($data['action'] === 'reassign') {
                PersonnelAssignment::query()->create([
                    'personnel_id' => $personnel->id,
                    'unit_id' => $data['unit_id'],
                    'assignment_type' => $data['assignment_type'],
                    'start_date' => $effective,
                    'order_reference' => $data['order_reference'] ?? null,
                    'created_by' => $request->user()->id,
                ]);
            } elseif (! empty($data['new_status'])) {
                $personnel->update(['service_status' => $data['new_status']]);
            }
        });

        return redirect()->route('personnel.show', [$personnel, 'tab' => 'assignments'])->with('success', 'Assignment history updated.');
    }
}
