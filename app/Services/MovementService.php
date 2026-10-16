<?php

namespace App\Services;

use App\Enums\AccountabilityCategory;
use App\Enums\ApplicationStatus;
use App\Enums\MovementStatus;
use App\Models\AccountabilityEvent;
use App\Models\DutyAssignment;
use App\Models\Personnel;
use App\Models\PersonnelApplication;
use App\Models\PersonnelMovement;
use App\Models\User;
use App\Models\VerificationCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Actual departures and returns (UC-04, UC-05).
 *
 * Planned dates are never overwritten by actual dates. Extensions keep the
 * original expected return; corrections require a reason and are audited.
 */
class MovementService
{
    public function __construct(protected AuditLogger $audit) {}

    public function createFromApplication(PersonnelApplication $app, User $actor): PersonnelMovement
    {
        $existing = PersonnelMovement::query()->where('application_id', $app->id)
            ->where('status', '!=', MovementStatus::Cancelled->value)->first();
        if ($existing) {
            return $existing;
        }

        $movement = PersonnelMovement::query()->create([
            'personnel_id' => $app->personnel_id,
            'application_id' => $app->id,
            'category' => $app->type->category,
            'destination' => $app->destination,
            'authority_reference' => $app->reference_number,
            'expected_departure_at' => $app->start_at,
            'expected_return_at' => $app->end_at,
            'original_expected_return_at' => $app->end_at,
            'status' => MovementStatus::Scheduled,
            'created_by' => $actor->id,
        ]);
        $this->audit->log('movement.scheduled', $movement, null, $movement->only(['personnel_id', 'category', 'expected_departure_at', 'expected_return_at']), 'Movement scheduled from approved application '.$app->reference_number);

        return $movement;
    }

    public function createFromDuty(DutyAssignment $duty, User $actor): PersonnelMovement
    {
        $movement = PersonnelMovement::query()->create([
            'personnel_id' => $duty->personnel_id,
            'duty_assignment_id' => $duty->id,
            'category' => $duty->assignment_type->category(),
            'destination' => $duty->location,
            'authority_reference' => $duty->reference_number,
            'expected_departure_at' => $duty->start_at,
            'expected_return_at' => $duty->end_at,
            'original_expected_return_at' => $duty->end_at,
            'status' => MovementStatus::Scheduled,
            'created_by' => $actor->id,
        ]);
        $this->audit->log('movement.scheduled', $movement, null, $movement->only(['personnel_id', 'category', 'expected_departure_at', 'expected_return_at']), 'Movement scheduled from duty assignment: '.$duty->title);

        return $movement;
    }

    /** Authorized movement that does not require an application (FR 2.5). */
    public function createDirect(User $actor, Personnel $personnel, array $data): PersonnelMovement
    {
        $category = AccountabilityCategory::from($data['category']);
        if (! in_array($category, AccountabilityCategory::awayCategories(), true)) {
            throw ValidationException::withMessages(['category' => 'Invalid movement category.']);
        }
        if (Carbon::parse($data['expected_return_at'])->lte(Carbon::parse($data['expected_departure_at']))) {
            throw ValidationException::withMessages(['expected_return_at' => 'Expected return must be after departure.']);
        }

        return DB::transaction(function () use ($actor, $personnel, $data, $category) {
            $movement = PersonnelMovement::query()->create([
                'personnel_id' => $personnel->id,
                'category' => $category,
                'destination' => $data['destination'] ?? null,
                'authority_reference' => $data['authority_reference'],
                'expected_departure_at' => $data['expected_departure_at'],
                'expected_return_at' => $data['expected_return_at'],
                'original_expected_return_at' => $data['expected_return_at'],
                'status' => MovementStatus::Scheduled,
                'created_by' => $actor->id,
            ]);
            $this->audit->log('movement.created_direct', $movement, null, $movement->only(['personnel_id', 'category', 'authority_reference']), 'Direct authorized movement created', $data['remarks'] ?? null);

            if (! empty($data['depart_now'])) {
                $this->recordDeparture($actor, $movement, now(), $data['remarks'] ?? null);
            }

            return $movement->fresh();
        });
    }

    public function recordDeparture(User $actor, PersonnelMovement $movement, Carbon $at, ?string $remarks = null): PersonnelMovement
    {
        return DB::transaction(function () use ($actor, $movement, $at, $remarks) {
            // Lock the member row so two clerks cannot open conflicting movements (BR-012).
            Personnel::query()->whereKey($movement->personnel_id)->lockForUpdate()->first();
            $movement = PersonnelMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();

            if ($movement->status !== MovementStatus::Scheduled) {
                throw ValidationException::withMessages(['movement' => 'Departure can only be recorded for a scheduled movement (current: '.$movement->status->shortLabel().').']);
            }
            if ($at->gt(now()->addMinutes(5))) {
                throw ValidationException::withMessages(['actual_at' => 'The departure time cannot be in the future.']);
            }
            if ($movement->application && $movement->application->status !== ApplicationStatus::Approved) {
                throw ValidationException::withMessages(['movement' => 'The linked application is not approved.']);
            }
            $open = PersonnelMovement::query()
                ->where('personnel_id', $movement->personnel_id)
                ->where('status', MovementStatus::Departed->value)
                ->whereKeyNot($movement->id)
                ->first();
            if ($open) {
                throw ValidationException::withMessages(['movement' => 'This member already has an open movement (departed '.$open->actual_departure_at->format('d M Y H:i').'). Record that return first.']);
            }

            $movement->update([
                'actual_departure_at' => $at,
                'status' => MovementStatus::Departed,
                'departure_recorded_by' => $actor->id,
                'departure_remarks' => $remarks,
            ]);
            $movement->dutyAssignment?->update(['status' => 'ongoing']);
            $this->audit->log('movement.departed', $movement, ['status' => 'scheduled'], ['status' => 'departed', 'actual_departure_at' => $at], 'Actual departure recorded', $remarks);

            return $movement;
        });
    }

    public function recordReturn(User $actor, PersonnelMovement $movement, Carbon $at, ?string $remarks = null): PersonnelMovement
    {
        return DB::transaction(function () use ($actor, $movement, $at, $remarks) {
            $movement = PersonnelMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            if ($movement->status !== MovementStatus::Departed) {
                throw ValidationException::withMessages(['movement' => 'Return can only be recorded for a member who is recorded as away.']);
            }
            if ($at->lt($movement->actual_departure_at)) {
                throw ValidationException::withMessages(['actual_at' => 'Return time cannot be earlier than the recorded departure.']);
            }
            if ($at->gt(now()->addMinutes(5))) {
                throw ValidationException::withMessages(['actual_at' => 'The return time cannot be in the future.']);
            }

            $wasOverdue = $movement->isOverdueAt($at) || $at->gt($movement->overdueDeadline());
            $movement->update([
                'actual_return_at' => $at,
                'status' => MovementStatus::Returned,
                'return_recorded_by' => $actor->id,
                'return_remarks' => $remarks,
            ]);

            if ($movement->application) {
                app(ApplicationWorkflowService::class)->close($movement->application, $actor, 'Return recorded '.$at->format('d M Y H:i'));
            }
            if ($movement->dutyAssignment && $movement->dutyAssignment->status !== 'cancelled') {
                $movement->dutyAssignment->update(['status' => 'completed']);
            }

            // Resolve open overdue cases for this movement with a traceable note.
            VerificationCase::query()->where('personnel_movement_id', $movement->id)->where('status', 'open')
                ->get()->each(function (VerificationCase $case) use ($actor, $at) {
                    $case->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                        'resolved_by' => $actor->id,
                        'resolution' => 'Return recorded at '.$at->format('d M Y H:i').'.',
                    ]);
                });

            $this->audit->log('movement.returned_home', $movement, ['status' => 'departed'], [
                'status' => 'returned', 'actual_return_at' => $at, 'late' => $wasOverdue,
            ], 'Actual return recorded'.($wasOverdue ? ' (after expected return)' : ''), $remarks);

            return $movement;
        });
    }

    /** Authorized extension: new expected return; original stays recorded. */
    public function extend(User $actor, PersonnelMovement $movement, Carbon $newReturn, string $reason): PersonnelMovement
    {
        if (in_array($movement->status, [MovementStatus::Returned, MovementStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['movement' => 'Closed movements cannot be extended.']);
        }
        if ($newReturn->lte($movement->expected_departure_at)) {
            throw ValidationException::withMessages(['expected_return_at' => 'New expected return must be after the departure.']);
        }
        $before = ['expected_return_at' => $movement->expected_return_at];
        $movement->update(['expected_return_at' => $newReturn]);
        $this->audit->log('movement.extended', $movement, $before, ['expected_return_at' => $newReturn], 'Expected return changed', $reason);

        return $movement;
    }

    /** Correction of actual timestamps (e.g. return not encoded on time, UC-05). */
    public function correct(User $actor, PersonnelMovement $movement, array $data, string $reason): PersonnelMovement
    {
        return DB::transaction(function () use ($actor, $movement, $data, $reason) {
            $movement = PersonnelMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            $changes = [];
            foreach (['actual_departure_at', 'actual_return_at'] as $field) {
                if (array_key_exists($field, $data) && $data[$field]) {
                    $changes[$field] = Carbon::parse($data[$field]);
                }
            }
            $dep = $changes['actual_departure_at'] ?? $movement->actual_departure_at;
            $ret = $changes['actual_return_at'] ?? $movement->actual_return_at;
            if (! $dep && $ret) {
                throw ValidationException::withMessages(['actual_return_at' => 'A return cannot be recorded without a departure.']);
            }
            if ($dep && $ret && $ret->lt($dep)) {
                throw ValidationException::withMessages(['actual_return_at' => 'Return cannot be earlier than departure.']);
            }
            if ($changes === []) {
                throw ValidationException::withMessages(['actual_departure_at' => 'Enter at least one corrected value.']);
            }

            $before = $movement->only(array_keys($changes) + ['status' => 'status']);
            $status = $ret ? MovementStatus::Returned : ($dep ? MovementStatus::Departed : $movement->status);
            $movement->fill($changes);
            $movement->status = $status;
            if (isset($changes['actual_departure_at']) && ! $movement->departure_recorded_by) {
                $movement->departure_recorded_by = $actor->id;
            }
            if (isset($changes['actual_return_at']) && ! $movement->return_recorded_by) {
                $movement->return_recorded_by = $actor->id;
            }
            $movement->save();

            if ($status === MovementStatus::Returned && $movement->application) {
                app(ApplicationWorkflowService::class)->close($movement->application, $actor, 'Return recorded by correction');
            }
            if ($status === MovementStatus::Returned) {
                VerificationCase::query()->where('personnel_movement_id', $movement->id)->where('status', 'open')
                    ->update(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => $actor->id, 'resolution' => 'Resolved by movement correction: '.$reason]);
            }

            $this->audit->log('movement.corrected', $movement, $before, $movement->only(array_keys($changes) + ['status' => 'status']), 'Movement timestamps corrected', $reason);

            return $movement;
        });
    }

    public function cancel(PersonnelMovement $movement, User $actor, string $reason): void
    {
        if ($movement->actual_departure_at) {
            throw ValidationException::withMessages(['movement' => 'A movement with a recorded departure cannot be cancelled. Use a correction.']);
        }
        $movement->update(['status' => MovementStatus::Cancelled]);
        $this->audit->log('movement.cancelled', $movement, ['status' => 'scheduled'], ['status' => 'cancelled'], 'Scheduled movement cancelled', $reason);
    }

    /** Muster / verification events (one per member). */
    public function recordEvents(User $actor, array $personnelIds, string $type, Carbon $at, ?string $remarks, string $source = 'muster'): int
    {
        if (! array_key_exists($type, AccountabilityEvent::TYPES)) {
            throw ValidationException::withMessages(['event_type' => 'Invalid accountability entry.']);
        }
        if ($at->gt(now()->addMinutes(5))) {
            throw ValidationException::withMessages(['occurred_at' => 'The muster time cannot be in the future.']);
        }

        return DB::transaction(function () use ($actor, $personnelIds, $type, $at, $remarks, $source) {
            $count = 0;
            foreach (array_unique($personnelIds) as $id) {
                AccountabilityEvent::query()->create([
                    'personnel_id' => $id,
                    'event_type' => $type,
                    'occurred_at' => $at,
                    'source' => $source,
                    'recorded_by' => $actor->id,
                    'remarks' => $remarks,
                ]);
                $count++;
            }
            $this->audit->log('accountability.recorded', null, null, [
                'event_type' => $type, 'count' => $count, 'occurred_at' => $at, 'personnel_ids' => array_values(array_unique($personnelIds)),
            ], 'Accountability entries recorded ('.(AccountabilityEvent::TYPES[$type]).')', $remarks);

            return $count;
        });
    }

    public function voidEvent(User $actor, AccountabilityEvent $event, string $reason): void
    {
        if ($event->voided_at) {
            return;
        }
        $event->update(['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason]);
        $this->audit->log('accountability.voided', $event, ['event_type' => $event->event_type], ['voided' => true], 'Accountability entry voided', $reason);
    }
}
