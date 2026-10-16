<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PersonnelApplication extends Model
{
    protected $table = 'personnel_applications';

    protected $fillable = [
        'reference_number', 'personnel_id', 'application_type_id', 'approval_workflow_id',
        'current_step_id', 'created_by', 'status', 'purpose', 'destination', 'contact_while_away',
        'start_at', 'end_at', 'is_late', 'is_retrospective', 'late_reason', 'submitted_at',
        'decided_at', 'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    protected $casts = [
        'status' => ApplicationStatus::class,
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'is_late' => 'boolean',
        'is_retrospective' => 'boolean',
    ];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ApplicationType::class, 'application_type_id');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalStep::class, 'current_step_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ApplicationDecision::class, 'application_id')->orderBy('decided_at')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class, 'application_id');
    }

    public function activeDocuments(): HasMany
    {
        return $this->documents()->where('is_superseded', false);
    }

    public function movement(): HasOne
    {
        return $this->hasOne(PersonnelMovement::class, 'application_id')->latestOfMany();
    }

    public function durationDays(): float
    {
        return round($this->start_at->diffInMinutes($this->end_at) / 1440, 1);
    }

    public function durationLabel(): string
    {
        $minutes = (int) $this->start_at->diffInMinutes($this->end_at);
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $parts = [];
        if ($days) {
            $parts[] = $days.' '.str('day')->plural($days);
        }
        if ($hours) {
            $parts[] = $hours.' '.str('hr')->plural($hours);
        }

        return $parts ? implode(' ', $parts) : $minutes.' min';
    }

    public function isSensitive(): bool
    {
        return (bool) $this->type?->is_sensitive;
    }

    /** Required documents effective at submission (or today for drafts). */
    public function requirementChecklist(): array
    {
        $date = $this->submitted_at ?? now();
        $requirements = $this->type->requirementsOn($date);
        $docs = $this->relationLoaded('documents') ? $this->documents->where('is_superseded', false) : $this->activeDocuments()->get();

        return $requirements->map(function (DocumentRequirement $req) use ($docs) {
            $doc = $docs->where('document_requirement_id', $req->id)->sortByDesc('id')->first();

            return [
                'requirement' => $req,
                'document' => $doc,
                'state' => match (true) {
                    $doc === null => 'missing',
                    $doc->review_status === 'verified' => 'verified',
                    $doc->review_status === 'rejected' => 'rejected',
                    default => 'uploaded',
                },
            ];
        })->all();
    }

    public function missingRequiredDocuments(): array
    {
        return collect($this->requirementChecklist())
            ->filter(fn ($row) => $row['requirement']->is_required && in_array($row['state'], ['missing', 'rejected'], true))
            ->pluck('requirement.name')->all();
    }

    public function unverifiedRequiredDocuments(): array
    {
        return collect($this->requirementChecklist())
            ->filter(fn ($row) => $row['requirement']->is_required && $row['state'] !== 'verified')
            ->pluck('requirement.name')->all();
    }

    public function scopeOverlapping(Builder $q, int $personnelId, $start, $end, ?int $ignoreId = null): Builder
    {
        return $q->where('personnel_id', $personnelId)
            ->whereIn('status', ApplicationStatus::blocking())
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId));
    }
}
