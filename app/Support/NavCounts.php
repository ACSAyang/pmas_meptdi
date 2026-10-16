<?php

namespace App\Support;

use App\Enums\ApplicationStatus;
use App\Enums\MovementStatus;
use App\Models\ApprovalStep;
use App\Models\PersonnelApplication;
use App\Models\PersonnelMovement;
use App\Models\User;
use App\Models\VerificationCase;

/** Small counters displayed in the sidebar. */
class NavCounts
{
    public static function for(User $user): array
    {
        $counts = ['approvals' => 0, 'overdue' => 0, 'cases' => 0, 'mine' => 0];

        if ($user->hasPermission('applications.act')) {
            $counts['approvals'] = static::approvalQueue($user)->count();
        }
        if ($user->hasPermission('movements.view')) {
            $grace = Settings::int('overdue_grace_minutes', 0);
            $counts['overdue'] = PersonnelMovement::query()
                ->where('status', MovementStatus::Departed->value)
                ->where('expected_return_at', '<', now()->subMinutes($grace))
                ->count();
        }
        if ($user->hasPermission('accountability.verify')) {
            $counts['cases'] = VerificationCase::query()->where('status', 'open')->count();
        }
        if ($user->personnel_id) {
            $counts['mine'] = PersonnelApplication::query()->where('personnel_id', $user->personnel_id)
                ->whereIn('status', [ApplicationStatus::Returned->value])->count();
        }

        return $counts;
    }

    /** Pending applications waiting at a step assigned to one of the user's roles. */
    public static function approvalQueue(User $user)
    {
        $stepIds = ApprovalStep::query()->whereIn('role_id', $user->roles->pluck('id'))->pluck('id');

        return PersonnelApplication::query()
            ->where('status', ApplicationStatus::Pending->value)
            ->whereIn('current_step_id', $stepIds)
            ->when($user->personnel_id, fn ($q) => $q->where('personnel_id', '!=', $user->personnel_id))
            ->whereDoesntHave('decisions', fn ($q) => $q->where('actor_user_id', $user->id)
                ->whereIn('decision', ['recommended', 'approved'])
                ->whereColumn('application_decisions.decided_at', '>=', 'personnel_applications.submitted_at'));
    }
}
