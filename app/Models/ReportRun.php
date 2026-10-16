<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportRun extends Model
{
    protected $fillable = [
        'report_type', 'title', 'as_of_at', 'period_from', 'period_to', 'parameters', 'summary',
        'payload', 'generated_by', 'generated_at', 'status', 'certified_by', 'certified_at', 'remarks',
    ];

    protected $casts = [
        'as_of_at' => 'datetime',
        'period_from' => 'datetime',
        'period_to' => 'datetime',
        'generated_at' => 'datetime',
        'certified_at' => 'datetime',
        'parameters' => 'array',
        'summary' => 'array',
    ];

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function certifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certified_by');
    }

    public function rows(): array
    {
        return json_decode($this->payload ?? '[]', true) ?: [];
    }
}
