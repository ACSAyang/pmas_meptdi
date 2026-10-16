<?php

namespace App\Models;

use App\Enums\DutyType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DutyAssignment extends Model
{
    use Auditable;

    public const STATUSES = [
        'scheduled' => 'Scheduled',
        'ongoing' => 'Ongoing',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'personnel_id', 'assignment_type', 'title', 'location', 'start_at', 'end_at',
        'reference_number', 'responsible_office', 'is_away', 'status', 'remarks', 'created_by',
    ];

    protected $casts = [
        'assignment_type' => DutyType::class,
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_away' => 'boolean',
    ];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movement(): HasOne
    {
        return $this->hasOne(PersonnelMovement::class)->latestOfMany();
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'ongoing' => 'blue',
            'completed' => 'green',
            'cancelled' => 'gray',
            default => 'amber',
        };
    }
}
