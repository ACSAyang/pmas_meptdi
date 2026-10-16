<?php

namespace App\Policies;

use App\Models\Personnel;
use App\Models\User;

class PersonnelPolicy
{
    public function view(User $user, Personnel $personnel): bool
    {
        return $user->hasPermission('personnel.view') || $user->personnel_id === $personnel->id;
    }

    public function viewSensitive(User $user, Personnel $personnel): bool
    {
        return $user->hasPermission('personnel.view_sensitive') || $user->personnel_id === $personnel->id;
    }

    public function update(User $user, Personnel $personnel): bool
    {
        return $user->hasPermission('personnel.manage');
    }
}
