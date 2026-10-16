<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountabilityEvent extends Model
{
    public const TYPES = [
        'present' => 'Present / Accounted',
        'sick_call' => 'Sick Call / Medical',
        'absent' => 'Absent / Not Located',
    ];

    public const SOURCES = [
        'muster' => 'Muster / Roll Call',
        'return' => 'Return Recorded',
        'manual' => 'Manual Verification',
        'case' => 'Verification Case',
    ];

    protected $fillable = [
        'personnel_id', 'event_type', 'occurred_at', 'source', 'personnel_movement_id',
        'recorded_by', 'remarks', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function scopeValid(Builder $q): Builder
    {
        return $q->whereNull('voided_at');
    }

    public function label(): string
    {
        return self::TYPES[$this->event_type] ?? $this->event_type;
    }

    public function tone(): string
    {
        return match ($this->event_type) {
            'present' => 'green',
            'sick_call' => 'violet',
            'absent' => 'rose',
            default => 'gray',
        };
    }
}
