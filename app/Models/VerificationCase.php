<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VerificationCase extends Model
{
    public const REASONS = [
        'overdue' => 'Overdue return',
        'unverified' => 'Status unverified',
        'unaccounted' => 'Reported absent / not located',
        'conflict' => 'Conflicting records',
    ];

    protected $fillable = [
        'personnel_id', 'personnel_movement_id', 'reason', 'status', 'opened_at', 'opened_by',
        'resolved_at', 'resolved_by', 'resolution',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(PersonnelMovement::class, 'personnel_movement_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(VerificationCaseAction::class)->latest();
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
