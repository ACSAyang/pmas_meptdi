<?php

namespace App\Models;

use App\Enums\AccountabilityCategory;
use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationType extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'name', 'description', 'category', 'lead_time_days', 'max_duration_days',
        'requires_destination', 'requires_movement', 'is_sensitive', 'allow_on_behalf',
        'color', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'category' => AccountabilityCategory::class,
        'requires_destination' => 'boolean',
        'requires_movement' => 'boolean',
        'is_sensitive' => 'boolean',
        'allow_on_behalf' => 'boolean',
        'is_active' => 'boolean',
        'lead_time_days' => 'integer',
        'max_duration_days' => 'integer',
    ];

    public function documentRequirements(): HasMany
    {
        return $this->hasMany(DocumentRequirement::class)->orderBy('sort_order');
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(ApprovalWorkflow::class)->orderByDesc('version');
    }

    public function activeWorkflow(): ?ApprovalWorkflow
    {
        return $this->workflows()->where('is_active', true)->with('steps.role')->first();
    }

    /** Checklist effective on a date (versioned requirements, FR 2.3). */
    public function requirementsOn(CarbonInterface $date)
    {
        $d = $date->toDateString();

        return $this->documentRequirements()
            ->whereDate('effective_from', '<=', $d)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $d))
            ->get();
    }
}
