<?php

namespace App\Services;

use App\Enums\AccountabilityCategory;
use App\Enums\MovementStatus;
use App\Models\Personnel;
use App\Models\PersonnelMovement;
use App\Models\User;
use App\Models\VerificationCase;
use App\Notifications\OverdueReturnAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Verification cases for overdue, unverified and unaccounted members (UC-05).
 * Overdue status alone is not proof of misconduct: cases record verification
 * attempts and are resolved only with a reliable, recorded update.
 */
class VerificationService
{
    public function __construct(protected AuditLogger $audit, protected MovementService $movements) {}

    /** Opens a case for each newly overdue movement. Returns the number opened. */
    public function openOverdueCases(): int
    {
        $opened = 0;
        PersonnelMovement::query()
            ->where('status', MovementStatus::Departed->value)
            ->where('expected_return_at', '<', now())
            ->with(['personnel.rank', 'application.type', 'dutyAssignment'])
            ->get()
            ->filter(fn (PersonnelMovement $m) => $m->isOverdue())
            ->each(function (PersonnelMovement $m) use (&$opened) {
                $exists = VerificationCase::query()->where('personnel_movement_id', $m->id)->where('status', 'open')->exists();
                if ($exists) {
                    return;
                }
                $case = VerificationCase::query()->create([
                    'personnel_id' => $m->personnel_id,
                    'personnel_movement_id' => $m->id,
                    'reason' => 'overdue',
                    'status' => 'open',
                    'opened_at' => now(),
                ]);
                $this->audit->log('verification.opened', $case, null, ['reason' => 'overdue', 'movement_id' => $m->id], 'Overdue return flagged automatically');
                Notification::send(User::withRole('duty_watch')->get(), new OverdueReturnAlert($m, $case));
                $opened++;
            });

        return $opened;
    }

    public function open(User $actor, Personnel $personnel, string $reason, ?string $notes): VerificationCase
    {
        $existing = VerificationCase::query()->where('personnel_id', $personnel->id)->where('status', 'open')->first();
        if ($existing) {
            return $existing;
        }
        $movement = $personnel->movements()->where('status', MovementStatus::Departed->value)->latest('actual_departure_at')->first();
        $case = VerificationCase::query()->create([
            'personnel_id' => $personnel->id,
            'personnel_movement_id' => $movement?->id,
            'reason' => $reason,
            'status' => 'open',
            'opened_at' => now(),
            'opened_by' => $actor->id,
        ]);
        if ($notes) {
            $case->actions()->create(['user_id' => $actor->id, 'action_type' => 'note', 'details' => $notes]);
        }
        $this->audit->log('verification.opened', $case, null, ['reason' => $reason], 'Verification case opened', $notes);

        return $case;
    }

    public function addAction(User $actor, VerificationCase $case, string $type, string $details): void
    {
        if ($case->status !== 'open') {
            throw ValidationException::withMessages(['details' => 'This case is already resolved.']);
        }
        $case->actions()->create(['user_id' => $actor->id, 'action_type' => $type, 'details' => $details]);
        $this->audit->log('verification.action', $case, null, ['action_type' => $type], 'Verification action recorded', $details);
    }

    /**
     * Resolve with an authorized update. Outcomes:
     *  - returned: record the actual return of the open movement
     *  - extended: authorized extension of the expected return
     *  - present: member located and confirmed present (muster entry)
     *  - other: documented resolution without a status change
     */
    public function resolve(User $actor, VerificationCase $case, string $outcome, array $data, string $resolution): VerificationCase
    {
        if ($case->status !== 'open') {
            throw ValidationException::withMessages(['resolution' => 'This case is already resolved.']);
        }

        return DB::transaction(function () use ($actor, $case, $outcome, $data, $resolution) {
            $movement = $case->movement;
            switch ($outcome) {
                case 'returned':
                    if (! $movement || $movement->status !== MovementStatus::Departed) {
                        throw ValidationException::withMessages(['outcome' => 'There is no open movement to close.']);
                    }
                    $this->movements->recordReturn($actor, $movement, \Illuminate\Support\Carbon::parse($data['actual_at'] ?? now()), 'Verification case #'.$case->id.': '.$resolution);
                    break;
                case 'extended':
                    if (! $movement || empty($data['new_return_at'])) {
                        throw ValidationException::withMessages(['new_return_at' => 'Enter the authorized new expected return.']);
                    }
                    $this->movements->extend($actor, $movement, \Illuminate\Support\Carbon::parse($data['new_return_at']), 'Verification case #'.$case->id.': '.$resolution);
                    break;
                case 'present':
                    if ($movement && $movement->status === MovementStatus::Departed) {
                        throw ValidationException::withMessages(['outcome' => 'The member still has an open movement. Use "Return recorded" instead.']);
                    }
                    $this->movements->recordEvents($actor, [$case->personnel_id], 'present', now(), 'Verification case #'.$case->id.': '.$resolution, 'case');
                    break;
            }

            $case->refresh();
            if ($case->status === 'open') {
                $case->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'resolved_by' => $actor->id,
                    'resolution' => $resolution,
                ]);
            } else {
                $case->update(['resolution' => $resolution]);
            }
            $this->audit->log('verification.resolved', $case, ['status' => 'open'], ['status' => 'resolved', 'outcome' => $outcome], 'Verification case resolved', $resolution);

            return $case;
        });
    }

    public static function categoryReason(AccountabilityCategory $category): string
    {
        return match ($category) {
            AccountabilityCategory::Overdue => 'overdue',
            AccountabilityCategory::Unaccounted => 'unaccounted',
            default => 'unverified',
        };
    }
}
