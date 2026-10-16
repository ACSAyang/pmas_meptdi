<?php

namespace App\Policies;

use App\Enums\ApplicationStatus as Status;
use App\Models\PersonnelApplication;
use App\Models\User;
use App\Services\ApplicationWorkflowService;

/**
 * Server-side access rules for applications (permission matrix, section 4).
 */
class PersonnelApplicationPolicy
{
    public function isOwner(User $user, PersonnelApplication $app): bool
    {
        return ($user->personnel_id && $user->personnel_id === $app->personnel_id)
            || $user->id === $app->created_by;
    }

    protected function isWorkflowActor(User $user, PersonnelApplication $app): bool
    {
        if ($app->status === Status::Pending && $app->currentStep && $user->roles->contains('id', $app->currentStep->role_id)) {
            return true;
        }

        return $app->decisions()->where('actor_user_id', $user->id)->whereNotNull('approval_step_id')->exists();
    }

    public function view(User $user, PersonnelApplication $app): bool
    {
        if ($this->isOwner($user, $app) || $this->isWorkflowActor($user, $app)) {
            return true;
        }
        if ($user->hasPermission('applications.view_all')) {
            return ! $app->isSensitive() || $user->hasPermission('medical.view');
        }

        return false;
    }

    /** Whether purpose/attachments of a sensitive (medical) application may be shown. */
    public function viewSensitiveDetails(User $user, PersonnelApplication $app): bool
    {
        return ! $app->isSensitive()
            || $this->isOwner($user, $app)
            || $user->hasPermission('medical.view')
            || $this->isWorkflowActor($user, $app);
    }

    public function update(User $user, PersonnelApplication $app): bool
    {
        return $app->status->isEditable() && $this->isOwner($user, $app);
    }

    public function submit(User $user, PersonnelApplication $app): bool
    {
        return $this->update($user, $app);
    }

    public function uploadDocument(User $user, PersonnelApplication $app): bool
    {
        return in_array($app->status, [Status::Draft, Status::Returned, Status::Pending], true)
            && ($this->isOwner($user, $app) || $user->hasPermission('documents.verify'));
    }

    public function viewDocument(User $user, PersonnelApplication $app): bool
    {
        return $this->view($user, $app) && $this->viewSensitiveDetails($user, $app);
    }

    public function verifyDocuments(User $user, PersonnelApplication $app): bool
    {
        return $user->hasPermission('documents.verify')
            && $this->viewSensitiveDetails($user, $app)
            && ! ($user->personnel_id && $user->personnel_id === $app->personnel_id);
    }

    public function act(User $user, PersonnelApplication $app): bool
    {
        return app(ApplicationWorkflowService::class)->canAct($user, $app);
    }

    public function cancel(User $user, PersonnelApplication $app): bool
    {
        if (in_array($app->status, [Status::Draft, Status::Pending, Status::Returned], true)) {
            return $this->isOwner($user, $app) || $user->hasPermission('applications.cancel_approved');
        }
        if ($app->status === Status::Approved) {
            return $user->hasPermission('applications.cancel_approved') && ! $app->movement?->actual_departure_at;
        }

        return false;
    }
}
