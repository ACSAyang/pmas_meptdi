<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelAssignment extends Model
{
    use Auditable;

    public const TYPES = [
        'regular' => 'Regular',
        'attached' => 'Attached',
        'temporary' => 'Temporary',
    ];

    protected $fillable = [
        'personnel_id', 'unit_id', 'assignment_type', 'start_date', 'end_date',
        'order_reference', 'end_reason', 'remarks', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCurrent(): bool
    {
        return $this->end_date === null || $this->end_date->isToday() || $this->end_date->isFuture();
    }
}
