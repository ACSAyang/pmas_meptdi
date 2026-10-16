<?php

namespace App\Http\Controllers;

use App\Enums\DutyType;
use App\Enums\MovementStatus;
use App\Models\DutyAssignment;
use App\Models\Personnel;
use App\Services\AuditLogger;
use App\Services\MovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Training, schooling, official business, TDY and other assignments (FR-012).
 * Assignments away from the unit create a scheduled movement so the actual
 * departure/return is still verified by the Duty Office Watch.
 */
class DutyAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status', 'current');

        return view('duty.index', [
            'duties' => DutyAssignment::query()->with(['personnel.rank', 'movement'])
                ->when($status === 'current', fn ($q) => $q->whereIn('status', ['scheduled', 'ongoing']))
                ->when(in_array($status, ['completed', 'cancelled'], true), fn ($q) => $q->where('status', $status))
                ->when($request->filled('type'), fn ($q) => $q->where('assignment_type', $request->input('type')))
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$request->input('q').'%')
                    ->orWhereHas('personnel', fn ($p) => $p->where('last_name', 'like', '%'.$request->input('q').'%'))))
                ->orderBy('start_at', $status === 'current' ? 'asc' : 'desc')
                ->paginate(20)->withQueryString(),
            'status' => $status,
            'filters' => $request->only(['type', 'q']),
        ]);
    }

    public function show(DutyAssignment $duty): View
    {
        $duty->load(['personnel.rank', 'movement', 'creator']);

        return view('duty.show', ['d' => $duty]);
    }

    public function create(Request $request): View
    {
        return view('duty.form', ['d' => new DutyAssignment(['personnel_id' => $request->integer('personnel') ?: null, 'is_away' => true, 'start_at' => now()->addDay()->setTime(8, 0), 'end_at' => now()->addDays(5)->setTime(17, 0)]), 'personnel' => $this->personnelOptions()]);
    }

    public function store(Request $request, MovementService $movements): RedirectResponse
    {
        $data = $this->validated($request);
        $duty = DB::transaction(function () use ($data, $request, $movements) {
            $duty = DutyAssignment::query()->create($data + ['created_by' => $request->user()->id, 'status' => 'scheduled']);
            if ($duty->is_away) {
                $movements->createFromDuty($duty, $request->user());
            }

            return $duty;
        });

        return redirect()->route('duty.show', $duty)->with('success', 'Assignment recorded.'.($duty->is_away ? ' A scheduled movement was created for departure tracking.' : ''));
    }

    public function edit(DutyAssignment $duty): View
    {
        return view('duty.form', ['d' => $duty, 'personnel' => $this->personnelOptions()]);
    }

    public function update(Request $request, DutyAssignment $duty): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['personnel_id'], $data['is_away']);
        DB::transaction(function () use ($duty, $data, $request) {
            $duty->update($data);
            $m = $duty->movement;
            if ($m && $m->status === MovementStatus::Scheduled) {
                $m->update(['expected_departure_at' => $duty->start_at, 'expected_return_at' => $duty->end_at, 'original_expected_return_at' => $duty->end_at, 'destination' => $duty->location, 'authority_reference' => $duty->reference_number]);
            } elseif ($m && $m->status === MovementStatus::Departed && ! $m->expected_return_at->eq($duty->end_at)) {
                app(MovementService::class)->extend($request->user(), $m, $duty->end_at, 'Duty assignment end date updated');
            }
        });

        return redirect()->route('duty.show', $duty)->with('success', 'Assignment updated.');
    }

    public function cancel(Request $request, DutyAssignment $duty, MovementService $movements, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        if ($duty->movement?->actual_departure_at) {
            return back()->withErrors(['reason' => 'The member has already departed. Record the return on the movement instead.']);
        }
        DB::transaction(function () use ($duty, $movements, $request, $data, $audit) {
            $duty->update(['status' => 'cancelled']);
            if ($duty->movement && $duty->movement->status === MovementStatus::Scheduled) {
                $movements->cancel($duty->movement, $request->user(), 'Duty assignment cancelled: '.$data['reason']);
            }
            $audit->log('duty.cancelled', $duty, null, null, 'Duty assignment cancelled', $data['reason']);
        });

        return back()->with('success', 'Assignment cancelled.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'personnel_id' => ['required', 'exists:personnel,id'],
            'assignment_type' => ['required', Rule::enum(DutyType::class)],
            'title' => ['required', 'string', 'max:200'],
            'location' => ['nullable', 'string', 'max:200'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'responsible_office' => ['nullable', 'string', 'max:150'],
            'is_away' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['is_away'] = $request->boolean('is_away');

        return $data;
    }

    protected function personnelOptions()
    {
        return Personnel::query()->assignedOn(now())->where('service_status', 'active')->with('rank')->ordered()->get();
    }
}
