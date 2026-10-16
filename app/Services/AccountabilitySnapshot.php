<?php

namespace App\Services;

use App\Enums\AccountabilityCategory as Cat;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Result of an accountability calculation at one instant.
 */
class AccountabilitySnapshot
{
    /** @param Collection<int, AccountabilityRow> $rows */
    public function __construct(
        public CarbonInterface $asOf,
        public Collection $rows,
        public ?array $unitIds = null,
    ) {}

    public function assigned(): int
    {
        return $this->rows->count();
    }

    /** @return array<string, int> category value => count, in display order */
    public function counts(): array
    {
        $counts = array_fill_keys(array_map(fn ($c) => $c->value, Cat::cases()), 0);
        foreach ($this->rows as $row) {
            $counts[$row->category->value]++;
        }

        return $counts;
    }

    public function count(Cat ...$categories): int
    {
        $counts = $this->counts();

        return array_sum(array_map(fn ($c) => $counts[$c->value], $categories));
    }

    public function present(): int
    {
        return $this->count(Cat::Present);
    }

    public function authorizedAway(): int
    {
        return $this->count(Cat::Leave, Cat::Pass, Cat::Sick, Cat::Training, Cat::Official);
    }

    public function accounted(): int
    {
        return $this->present() + $this->authorizedAway();
    }

    public function unresolved(): int
    {
        return $this->count(Cat::Overdue, Cat::Unverified, Cat::Unaccounted);
    }

    /** Totals always reconcile to assigned strength. */
    public function reconciles(): bool
    {
        return $this->accounted() + $this->unresolved() === $this->assigned();
    }

    public function percent(int $value): float
    {
        return $this->assigned() ? round($value / $this->assigned() * 100, 1) : 0.0;
    }

    public function rowsIn(Cat ...$categories): Collection
    {
        return $this->rows->filter(fn ($r) => in_array($r->category, $categories, true))->values();
    }

    public function exceptions(): Collection
    {
        return $this->rowsIn(Cat::Overdue, Cat::Unaccounted, Cat::Unverified);
    }

    /** Unit x category matrix for the strength report. */
    public function byUnit(): Collection
    {
        return $this->rows->groupBy(fn ($r) => $r->unit?->id ?? 0)->map(function (Collection $rows) {
            $counts = array_fill_keys(array_map(fn ($c) => $c->value, Cat::cases()), 0);
            foreach ($rows as $r) {
                $counts[$r->category->value]++;
            }
            $unit = $rows->first()->unit;

            return [
                'unit' => $unit,
                'name' => $unit?->name ?? 'Unassigned',
                'code' => $unit?->code ?? '—',
                'sort' => $unit?->sort_order ?? 999,
                'assigned' => $rows->count(),
                'counts' => $counts,
            ];
        })->sortBy('sort')->values();
    }

    public function summaryArray(): array
    {
        return [
            'assigned' => $this->assigned(),
            'present' => $this->present(),
            'authorized_away' => $this->authorizedAway(),
            'accounted' => $this->accounted(),
            'unresolved' => $this->unresolved(),
            'counts' => $this->counts(),
        ];
    }
}
