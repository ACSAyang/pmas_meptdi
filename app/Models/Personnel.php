<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Personnel extends Model
{
    use Auditable;

    protected $table = 'personnel';

    public const STATUSES = [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'transferred' => 'Transferred Out',
        'separated' => 'Separated',
        'retired' => 'Retired',
    ];

    protected $fillable = [
        'service_number', 'rank_id', 'first_name', 'middle_name', 'last_name', 'suffix',
        'sex', 'designation', 'email', 'contact_number', 'emergency_contact_name',
        'emergency_contact_number', 'service_status', 'remarks',
    ];

    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PersonnelAssignment::class)->orderByDesc('start_date');
    }

    /** Current (open-ended) assignment. */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(PersonnelAssignment::class)->ofMany(['start_date' => 'max', 'id' => 'max'], function ($q) {
            $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString());
        });
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(PersonnelApplication::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(PersonnelMovement::class);
    }

    public function accountabilityEvents(): HasMany
    {
        return $this->hasMany(AccountabilityEvent::class);
    }

    public function dutyAssignments(): HasMany
    {
        return $this->hasMany(DutyAssignment::class);
    }

    /** "DELA CRUZ, Juan M. Jr." */
    public function getFullNameAttribute(): string
    {
        $middle = $this->middle_name ? ' '.mb_substr($this->middle_name, 0, 1).'.' : '';
        $suffix = $this->suffix ? ' '.$this->suffix : '';

        return mb_strtoupper($this->last_name).', '.$this->first_name.$middle.$suffix;
    }

    /** "PO2 Juan M. Dela Cruz" */
    public function getDisplayNameAttribute(): string
    {
        $middle = $this->middle_name ? ' '.mb_substr($this->middle_name, 0, 1).'.' : '';
        $suffix = $this->suffix ? ' '.$this->suffix : '';
        $rank = $this->rank?->abbreviation;

        return trim(($rank ? $rank.' ' : '').$this->first_name.$middle.' '.$this->last_name.$suffix);
    }

    /** "PO2 DELA CRUZ, Juan M." */
    public function getRankNameAttribute(): string
    {
        return trim(($this->rank?->abbreviation ?? '').' '.$this->full_name);
    }

    public function getInitialsAttribute(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    /** Members whose assignment covers the given date (assigned strength, BR-015). */
    public function scopeAssignedOn(Builder $query, CarbonInterface $at, ?array $unitIds = null): Builder
    {
        $date = $at->toDateString();

        return $query->whereHas('assignments', function (Builder $q) use ($date, $unitIds) {
            $q->whereDate('start_date', '<=', $date)
                ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date));
            if ($unitIds) {
                $q->whereIn('unit_id', $unitIds);
            }
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

        return $query->where(function ($q) use ($like) {
            $q->where('last_name', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('service_number', 'like', $like)
                ->orWhere('designation', 'like', $like);
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->leftJoin('ranks', 'ranks.id', '=', 'personnel.rank_id')
            ->orderBy('ranks.sort_order')
            ->orderBy('personnel.last_name')
            ->orderBy('personnel.first_name')
            ->select('personnel.*');
    }

    /** Assignment effective on a given date. */
    public function assignmentOn(CarbonInterface $at): ?PersonnelAssignment
    {
        $date = $at->toDateString();

        return $this->assignments
            ->first(fn ($a) => $a->start_date->toDateString() <= $date
                && ($a->end_date === null || $a->end_date->toDateString() >= $date));
    }
}
