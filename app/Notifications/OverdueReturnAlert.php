<?php

namespace App\Notifications;

use App\Models\PersonnelMovement;
use App\Models\VerificationCase;
use Illuminate\Notifications\Notification;

class OverdueReturnAlert extends Notification
{
    public function __construct(public PersonnelMovement $movement, public VerificationCase $case) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Overdue return: '.$this->movement->personnel->rank_name,
            'body' => 'Expected back '.$this->movement->expected_return_at->format('d M Y H:i').'. Verify status and record the outcome.',
            'url' => route('verification.show', $this->case),
            'tone' => 'red',
            'icon' => 'alert',
        ];
    }
}
