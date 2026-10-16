<?php

namespace App\Notifications;

use App\Models\PersonnelApplication;
use Illuminate\Notifications\Notification;

/** In-app notice: an application awaits the recipient's stage. Contains no sensitive detail. */
class ApplicationActionRequired extends Notification
{
    public function __construct(public PersonnelApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $type = $this->application->type->is_sensitive ? 'Restricted application' : $this->application->type->name;

        return [
            'title' => 'Action required: '.$this->application->reference_number,
            'body' => $type.' of '.$this->application->personnel->rank_name.' awaits '.$this->application->currentStep?->name.'.',
            'url' => route('applications.show', $this->application),
            'tone' => 'amber',
            'icon' => 'inbox',
        ];
    }
}
