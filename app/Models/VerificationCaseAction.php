<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationCaseAction extends Model
{
    public const TYPES = [
        'contact_attempt' => 'Contact attempt',
        'note' => 'Note',
        'escalation' => 'Escalated to chain of command',
    ];

    protected $fillable = ['verification_case_id', 'user_id', 'action_type', 'details'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::TYPES[$this->action_type] ?? $this->action_type;
    }
}
