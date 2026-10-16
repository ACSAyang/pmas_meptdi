<?php

namespace App\Http\Controllers;

use App\Enums\AccountabilityCategory as Cat;
use App\Models\AccountabilityEvent;
use App\Models\Unit;
use App\Services\AccountabilityService;
use App\Services\MovementService;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AccountabilityController extends Controller
{
    public function __construct(protected AccountabilityService $accountability, protected MovementService $movements) {}

    /** Live (or point-in-time) accountability board. */
    public function index(Request $request)
    {
        $asOf = $request->filled('as_of') ? Carbon::parse($request->input('as_of')) : now();
        $live = ! $request->filled('as_of') || $asOf->diffInMinutes(now(), true) < 1;
        $unit = $request->filled('unit') ? Unit::query()->find($request->integer('unit')) : null;
        $snapshot = $this->accountability->snapshot($asOf, $unit?->descendantIds());
        $canMed = $request->user()->hasPermission('medical.view');

        $rows = $snapshot->rows;
        if ($request->filled('category') && Cat::tryFrom($request->input('category'))) {
            $rows = $rows->filter(fn ($r) => $r->category->value === $request->input('category'));
        }
        if ($request->input('group') === 'unresolved') {
            $rows = $rows->filter(fn ($r) => $r->category->isUnresolved());
        } elseif ($request->input('group') === 'away') {
            $rows = $rows->filter(fn ($r) => $r->category->isAuthorizedAway());
        }
        if ($request->filled('q')) {
            $q = mb_strtolower($request->input('q'));
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r->personnel->full_name.' '.$r->personnel->service_number.' '.$r->personnel->rank?->abbreviation), $q));
        }

        if ($request->input('format') === 'csv') {
            return Csv::download('accountability-'.$asOf->format('Ymd-Hi').'.csv',
                ['As of', 'Service No.', 'Rank', 'Name', 'Unit', 'Category', 'Details', 'Since', 'Conflict'],
                $rows->map(fn ($r) => array_merge([$asOf->format('Y-m-d H:i')], array_values(collect($r->toArray($canMed))->only(['service_number', 'rank', 'name', 'unit', 'category_label', 'detail', 'since'])->all()), [$r->conflict ? 'Yes' : ''])));
        }

        return view('accountability.index', [
            'snapshot' => $snapshot,
            'rows' => $rows->values(),
            'live' => $live,
            'units' => Unit::options(),
            'unit' => $unit,
            'filters' => $request->only(['category', 'group', 'q', 'unit', 'as_of']),
            'canMed' => $canMed,
        ]);
    }

    /** Muster / roll-call sheet. */
    public function muster(Request $request)
    {
        $unit = $request->filled('unit') ? Unit::query()->find($request->integer('unit')) : null;
        $snapshot = $this->accountability->snapshot(now(), $unit?->descendantIds());
        $rows = $snapshot->rows;
        if ($request->filled('q')) {
            $q = mb_strtolower($request->input('q'));
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r->personnel->full_name.' '.$r->personnel->service_number), $q));
        }

        return view('accountability.muster', [
            'snapshot' => $snapshot,
            'rows' => $rows->values(),
            'units' => Unit::options(),
            'unit' => $unit,
            'canMed' => $request->user()->hasPermission('medical.view'),
            'recent' => AccountabilityEvent::query()->valid()->with(['personnel.rank', 'recorder'])->latest('created_at')->limit(10)->get(),
        ]);
    }

    public function record(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'personnel_ids' => ['required', 'array', 'min:1'],
            'personnel_ids.*' => ['integer', 'exists:personnel,id'],
            'event_type' => ['required', Rule::in(array_keys(AccountabilityEvent::TYPES))],
            'occurred_at' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ], ['personnel_ids.required' => 'Select at least one member.']);

        // Members recorded as away must be handled through their movement, not a muster entry.
        $away = \App\Models\PersonnelMovement::query()->whereIn('personnel_id', $data['personnel_ids'])
            ->where('status', 'departed')->with('personnel.rank')->get();
        if ($away->isNotEmpty() && $data['event_type'] === 'present') {
            return back()->withInput()->withErrors(['personnel_ids' => 'These members are recorded as away: '.$away->map(fn ($m) => $m->personnel->rank_name)->join('; ').'. Record their return instead of marking them present.']);
        }

        $count = $this->movements->recordEvents($request->user(), $data['personnel_ids'], $data['event_type'], Carbon::parse($data['occurred_at']), $data['remarks'] ?? null);

        return back()->with('success', $count.' member(s) recorded as "'.AccountabilityEvent::TYPES[$data['event_type']].'" at '.Carbon::parse($data['occurred_at'])->format('d M Y H:i').'.');
    }

    public function void(Request $request, AccountabilityEvent $event): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        $this->movements->voidEvent($request->user(), $event, $data['reason']);

        return back()->with('success', 'Entry voided. It remains in the record for audit.');
    }
}
