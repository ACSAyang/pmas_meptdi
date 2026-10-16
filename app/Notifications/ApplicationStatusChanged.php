<?php

namespace App\Notifications;

use App\Models\PersonnelApplication;
use Illuminate\Notifications\Notification;

class ApplicationStatusChanged extends Notification
{
    public function __construct(public PersonnelApplication $application, public string $decision) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $ref = $this->application->reference_number;
        [$title, $tone] = match ($this->decision) {
            'approved' => ["$ref approved", 'green'],
            'rejected' => ["$ref rejected", 'red'],
            'returned' => ["$ref returned for correction", 'orange'],
            'recommended' => ["$ref forwarded to next stage", 'blue'],
            default => ["$ref updated", 'navy'],
        };

        return [
            'title' => $title,
            'body' => $this->decision === 'recommended'
                ? 'Now awaiting: '.($this->application->currentStep?->name ?? 'next stage').'.'
                : 'Status: '.$this->application->status->label().'.',
            'url' => route('applications.show', $this->application),
            'tone' => $tone,
            'icon' => 'file',
        ];
    }
}
