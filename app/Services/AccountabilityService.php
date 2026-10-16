<?php

namespace App\Services;

use App\Enums\AccountabilityCategory as Cat;
use App\Enums\MovementStatus;
use App\Models\AccountabilityEvent;
use App\Models\Personnel;
use App\Models\PersonnelMovement;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Derives each assigned member's primary accountability category at a
 * reporting instant from authoritative records (assignments, movements,
 * accountability events). Nothing here relies on a manually edited status field.
 *
 * Classification precedence for every member assigned at the instant:
 *
 *  1. Recorded departure, not yet returned at the instant
 *       -> "Overdue" if past expected return + grace, otherwise the movement's category.
 *       -> If a "present" muster was recorded after departure: conflicting records -> Unverified.
 *  2. Valid confirmation (muster / verified return) inside the validity window,
 *     recorded after any scheduled departure time
 *       -> present = Present, sick_call = Sick/Medical, absent = Unaccounted.
 *  3. Approved movement that should have started but departure was not recorded -> Unverified.
 *  4. Otherwise -> Unverified (never assumed present, BR-011).
 *
 * Each member falls into exactly one category (BR-010), so totals always reconcile
 * to assigned strength.
 */
class AccountabilityService
{
    /**
     * @param  array<int>|null  $unitIds
     */
    public function snapshot(?CarbonInterface $at = null, ?array $unitIds = null): AccountabilitySnapshot
    {
        $at = $at ? Carbon::instance($at) : now();

        $personnel = Personnel::query()
            ->assignedOn($at, $unitIds)
            ->with(['rank', 'assignments.unit'])
            ->ordered()
            ->get();

        $ids = $personnel->pluck('id');
        $validity = max(1, Settings::int('muster_validity_hours', 24));
        $windowStart = $at->copy()->subHours($validity);

        $movements = $ids->isEmpty() ? collect() : PersonnelMovement::query()
            ->whereIn('personnel_id', $ids)
            ->where('status', '!=', MovementStatus::Cancelled->value)
            ->where(function ($q) use ($at, $windowStart) {
                // away at the instant
                $q->where(function ($q) use ($at) {
                    $q->whereNotNull('actual_departure_at')
                        ->where('actual_departure_at', '<=', $at)
                        ->where(fn ($q) => $q->whereNull('actual_return_at')->orWhere('actual_return_at', '>', $at));
                })
                // scheduled to be away but departure not (yet) recorded at the instant
                    ->orWhere(function ($q) use ($at) {
                        $q->where('expected_departure_at', '<=', $at)
                            ->where('expected_return_at', '>', $at)
                            ->where(fn ($q) => $q->whereNull('actual_departure_at')->orWhere('actual_departure_at', '>', $at));
                    })
                // returned inside the validity window (counts as a verified return)
                    ->orWhere(function ($q) use ($at, $windowStart) {
                        $q->whereNotNull('actual_return_at')
                            ->where('actual_return_at', '<=', $at)
                            ->where('actual_return_at', '>', $windowStart);
                    });
            })
            ->with(['application.type', 'dutyAssignment'])
            ->get()
            ->groupBy('personnel_id');

        $events = $ids->isEmpty() ? collect() : AccountabilityEvent::query()
            ->valid()
            ->whereIn('personnel_id', $ids)
            ->where('occurred_at', '<=', $at)
            ->where('occurred_at', '>', $windowStart)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->groupBy('personnel_id');

        $rows = $personnel->map(fn (Personnel $p) => $this->classify(
            $p,
            $at,
            $movements->get($p->id, collect()),
            $events->get($p->id, collect()),
        ));

        return new AccountabilitySnapshot($at, $rows, $unitIds);
    }

    /** Status of a single member (used on profile pages). */
    public function statusOf(Personnel $personnel, ?CarbonInterface $at = null): ?AccountabilityRow
    {
        $unitId = $personnel->assignmentOn($at ?? now())?->unit_id;
        if (! $unitId) {
            return null;
        }

        return $this->snapshot($at, [$unitId])->rows->first(fn ($r) => $r->personnel->id === $personnel->id);
    }

    protected function classify(Personnel $p, Carbon $at, Collection $movements, Collection $events): AccountabilityRow
    {
        $assignment = $p->assignmentOn($at);
        $row = new AccountabilityRow($p, $assignment?->unit);

        // 1. Away on a recorded departure
        $away = $movements->filter(fn (PersonnelMovement $m) => $m->actual_departure_at
            && $m->actual_departure_at->lte($at)
            && (! $m->actual_return_at || $m->actual_return_at->gt($at)))
            ->sortBy('actual_departure_at')->values();

        if ($away->isNotEmpty()) {
            $m = $away->first();
            $row->movement = $m;
            $row->since = $m->actual_departure_at;

            $presentAfterDeparture = $events->first(fn ($e) => $e->event_type === 'present' && $e->occurred_at->gt($m->actual_departure_at));
            if ($away->count() > 1 || $presentAfterDeparture) {
                $row->category = Cat::Unverified;
                $row->conflict = true;
                $row->detail = $away->count() > 1
                    ? 'Conflicting records: more than one open movement'
                    : 'Conflicting records: mustered present while movement is still open — record the return';

                return $row;
            }

            if ($m->isOverdueAt($at)) {
                $row->category = Cat::Overdue;
                $row->detail = 'Expected back '.$m->expected_return_at->format('d M Y H:i').' — '.$m->sourceLabel();

                return $row;
            }

            $row->category = $m->category;
            $row->detail = $m->sourceLabel().($m->destination ? ' · '.$m->destination : '')
                .' · until '.$m->expected_return_at->format('d M H:i');

            return $row;
        }

        // Pending scheduled departure (if any) — confirmations older than it do not count.
        $pending = $movements->first(fn (PersonnelMovement $m) => $m->expected_departure_at->lte($at)
            && $m->expected_return_at->gt($at)
            && (! $m->actual_departure_at || $m->actual_departure_at->gt($at)));

        // 2. Latest valid confirmation (muster event or verified return)
        $confirmations = $events->map(fn (AccountabilityEvent $e) => [
            'type' => $e->event_type, 'at' => $e->occurred_at, 'event' => $e, 'label' => AccountabilityEvent::SOURCES[$e->source] ?? 'Muster',
        ]);
        foreach ($movements as $m) {
            if ($m->actual_return_at && $m->actual_return_at->lte($at)) {
                $confirmations->push(['type' => 'present', 'at' => $m->actual_return_at, 'event' => null, 'label' => 'Return recorded']);
            }
        }
        $latest = $confirmations->sortBy(fn ($c) => $c['at']->getTimestamp())->last();

        if ($latest && (! $pending || $latest['at']->gte($pending->expected_departure_at))) {
            $row->event = $latest['event'];
            $row->since = $latest['at'];
            [$row->category, $row->detail] = match ($latest['type']) {
                'sick_call' => [Cat::Sick, 'Sick call / medical — '.$latest['label']],
                'absent' => [Cat::Unaccounted, 'Reported absent at '.$latest['label']],
                default => [Cat::Present, $latest['label'].' at '.$latest['at']->format('d M H:i')],
            };

            return $row;
        }

        // 3. Authorized absence scheduled but departure not recorded
        if ($pending) {
            $row->movement = $pending;
            $row->category = Cat::Unverified;
            $row->detail = $pending->sourceLabel().' scheduled '.$pending->expected_departure_at->format('d M H:i').' — departure not recorded';

            return $row;
        }

        // 4. No confirmation in the validity window
        $row->category = Cat::Unverified;
        $row->detail = 'No muster or verification in the last '.Settings::int('muster_validity_hours', 24).' hours';

        return $row;
    }
}
