<?php

namespace App\Http\Controllers;

use App\Enums\MovementStatus;
use App\Models\PersonnelMovement;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Personnel availability calendar (FR-015): planned and actual absences per day. */
class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $month = $request->filled('month') ? Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth() : now()->startOfMonth();
        $gridStart = $month->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $unit = $request->filled('unit') ? Unit::query()->find($request->integer('unit')) : null;
        $canMed = $request->user()->hasPermission('medical.view');

        $movements = PersonnelMovement::query()
            ->with(['personnel.rank', 'application.type'])
            ->where('status', '!=', MovementStatus::Cancelled->value)
            ->where('expected_departure_at', '<=', $gridEnd)
            ->where(fn ($q) => $q->where('expected_return_at', '>=', $gridStart)->orWhereNull('actual_return_at'))
            ->when($unit, fn ($q) => $q->whereHas('personnel', fn ($p) => $p->assignedOn(now(), $unit->descendantIds())))
            ->get()
            ->map(function (PersonnelMovement $m) {
                $m->cal_start = ($m->actual_departure_at ?? $m->expected_departure_at)->copy()->startOfDay();
                $end = $m->actual_return_at ?? ($m->status === MovementStatus::Departed && $m->expected_return_at->isPast() ? now() : $m->expected_return_at);
                $m->cal_end = $end->copy()->endOfDay();

                return $m;
            })
            ->filter(fn ($m) => $m->cal_start->lte($gridEnd) && $m->cal_end->gte($gridStart));

        $days = [];
        for ($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay()) {
            $items = $movements->filter(fn ($m) => $m->cal_start->lte($d) && $m->cal_end->gte($d))->sortBy('personnel.last_name')->values();
            $days[] = ['date' => $d->copy(), 'items' => $items];
        }

        return view('calendar.index', compact('month', 'days', 'unit', 'canMed') + ['units' => Unit::options()]);
    }
}
