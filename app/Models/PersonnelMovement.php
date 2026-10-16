<?php

namespace App\Models;

use App\Enums\AccountabilityCategory;
use App\Enums\MovementStatus;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonnelMovement extends Model
{
    protected $fillable = [
        'personnel_id', 'application_id', 'duty_assignment_id', 'category', 'destination',
        'authority_reference', 'expected_departure_at', 'expected_return_at', 'original_expected_return_at',
        'actual_departure_at', 'actual_return_at', 'status', 'departure_recorded_by', 'departure_remarks',
        'return_recorded_by', 'return_remarks', 'created_by',
    ];

    protected $casts = [
        'category' => AccountabilityCategory::class,
        'status' => MovementStatus::class,
        'expected_departure_at' => 'datetime',
        'expected_return_at' => 'datetime',
        'original_expected_return_at' => 'datetime',
        'actual_departure_at' => 'datetime',
        'actual_return_at' => 'datetime',
    ];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(PersonnelApplication::class, 'application_id');
    }

    public function dutyAssignment(): BelongsTo
    {
        return $this->belongsTo(DutyAssignment::class);
    }

    public function departureRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'departure_recorded_by');
    }

    public function returnRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_recorded_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verificationCases(): HasMany
    {
        return $this->hasMany(VerificationCase::class);
    }

    /** Overdue deadline = expected return + configured grace period (BR-009). */
    public function overdueDeadline(): CarbonInterface
    {
        return $this->expected_return_at->copy()->addMinutes(Settings::int('overdue_grace_minutes', 0));
    }

    /** True if the member was away and past the deadline at the given instant. */
    public function isOverdueAt(CarbonInterface $at): bool
    {
        if (! $this->actual_departure_at || $this->actual_departure_at->gt($at)) {
            return false;
        }
        if ($this->actual_return_at && $this->actual_return_at->lte($at)) {
            return false;
        }

        return $at->gt($this->overdueDeadline());
    }

    public function isOverdue(): bool
    {
        return $this->status === MovementStatus::Departed && $this->isOverdueAt(now());
    }

    public function wasReturnedLate(): bool
    {
        return $this->actual_return_at && $this->actual_return_at->gt($this->overdueDeadline());
    }

    public function sourceLabel(): string
    {
        if ($this->application_id) {
            return $this->application?->type?->name ?? 'Application';
        }
        if ($this->duty_assignment_id) {
            return $this->dutyAssignment?->assignment_type?->label() ?? 'Duty assignment';
        }

        return 'Direct authority';
    }

    public function sourceReference(): ?string
    {
        return $this->application?->reference_number ?? $this->dutyAssignment?->reference_number ?? $this->authority_reference;
    }

    /** Movements whose actual away period covers the instant. */
    public function scopeAwayAt(Builder $q, CarbonInterface $at): Builder
    {
        return $q->whereNotNull('actual_departure_at')
            ->where('actual_departure_at', '<=', $at)
            ->where(fn ($q) => $q->whereNull('actual_return_at')->orWhere('actual_return_at', '>', $at))
            ->where('status', '!=', MovementStatus::Cancelled->value);
    }
}
