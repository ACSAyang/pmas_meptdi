<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflow extends Model
{
    use Auditable;

    protected $fillable = ['application_type_id', 'version', 'name', 'is_active', 'active_from', 'active_to'];

    protected $casts = [
        'is_active' => 'boolean',
        'active_from' => 'datetime',
        'active_to' => 'datetime',
    ];

    public function applicationType(): BelongsTo
    {
        return $this->belongsTo(ApplicationType::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('step_order');
    }

    public function firstStep(): ?ApprovalStep
    {
        return $this->steps()->first();
    }
}
