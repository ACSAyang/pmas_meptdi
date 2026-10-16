@extends('layouts.app')
@section('title', 'Dashboard')
@section('crumb', 'Overview')
@section('content')
@php use App\Enums\AccountabilityCategory as Cat; @endphp

<div class="page-head">
    <div>
        <h2>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ $user->personnel?->rank?->abbreviation }} {{ $user->personnel?->last_name ?? $user->name }}</h2>
        <div class="sub">{{ now()->format('l, d F Y') }} · {{ $user->roleNames() }}</div>
    </div>
    <div class="page-actions">
        @if($user->hasPermission('accountability.muster'))<a href="{{ route('muster.index') }}" class="btn btn-secondary"><x-icon name="muster" size="sm"/> Take muster</a>@endif
        @if($user->hasPermission('movements.record'))<a href="{{ route('movements.index') }}" class="btn btn-secondary"><x-icon name="move" size="sm"/> Record movement</a>@endif
        @if($user->hasPermission('applications.create'))<a href="{{ route('applications.create') }}" class="btn btn-primary"><x-icon name="plus" size="sm"/> New application</a>@endif
    </div>
</div>

@isset($snapshot)
    <div class="card mb-3">
        <div class="card-header">
            <h3><x-icon name="shield"/> Personnel strength &amp; accountability</h3>
            <form method="GET" class="row" style="gap:8px">
                <span class="badge tone-green" title="Calculated from current records"><span class="bdot"></span> LIVE · as of {{ $snapshot->asOf->format('d M Y H:i') }}</span>
                <select name="unit" class="select input-sm" style="width:auto;min-width:200px" data-autosubmit aria-label="Unit filter">
                    <option value="">All units / sections</option>
                    @foreach($units as $u)<option value="{{ $u->id }}" @selected($unit?->id === $u->id)>{{ $u->name }}</option>@endforeach
                </select>
            </form>
        </div>
        <div class="card-body">
            <div class="kpis">
                <x-kpi label="Assigned strength" :value="$snapshot->assigned()" tone="navy" icon="users" meta="Officially assigned today" :href="route('accountability.index', ['unit' => $unit?->id])"/>
                <x-kpi label="Accounted for" :value="$snapshot->accounted()" tone="green" icon="check-circle" :meta="$snapshot->percent($snapshot->accounted()).'% of assigned'"/>
                <x-kpi label="Present" :value="$snapshot->present()" tone="teal" icon="shield" meta="Verified by muster / return" :href="route('accountability.index', ['category' => 'present', 'unit' => $unit?->id])"/>
                <x-kpi label="Authorized away" :value="$snapshot->authorizedAway()" tone="blue" icon="move" meta="Leave · pass · sick · training · OB"/>
                <x-kpi label="Overdue returns" :value="$snapshot->count(Cat::Overdue)" tone="red" icon="clock" :alert="$snapshot->count(Cat::Overdue) > 0" meta="Past expected return" :href="route('accountability.index', ['category' => 'overdue', 'unit' => $unit?->id])"/>
                <x-kpi label="Unverified / unaccounted" :value="$snapshot->count(Cat::Unverified, Cat::Unaccounted)" tone="amber" icon="alert" :alert="$snapshot->count(Cat::Unaccounted) > 0" meta="Needs Duty Watch verification" :href="route('accountability.index', ['group' => 'unresolved', 'unit' => $unit?->id])"/>
            </div>
            <div class="mt-3">@include('partials.strength-breakdown', ['snapshot' => $snapshot, 'linkParams' => ['unit' => $unit?->id]])</div>
        </div>
    </div>

    <div class="grid grid-main">
        <div class="stack">
            <div class="card">
                <div class="card-header">
                    <h3><x-icon name="alert"/> Exceptions requiring verification <span class="badge tone-{{ $snapshot->exceptions()->count() ? 'red' : 'green' }} sm">{{ $snapshot->exceptions()->count() }}</span></h3>
                    <a href="{{ route('accountability.index', ['group' => 'unresolved']) }}" class="btn btn-ghost btn-sm">Open board <x-icon name="chevron-right" size="sm"/></a>
                </div>
                @if($snapshot->exceptions()->isEmpty())
                    <x-empty icon="check-circle" title="No exceptions" text="Every assigned member is accounted for at this time."/>
                @else
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Member</th><th>Status</th><th>Details</th><th>Unit</th></tr></thead>
                    <tbody>
                    @foreach($snapshot->exceptions()->take(8) as $row)
                        <tr data-href="{{ route('personnel.show', $row->personnel) }}">
                            <td><x-person :p="$row->personnel"/></td>
                            <td><x-category-badge :category="$row->category"/>@if($row->conflict) <x-badge tone="orange" class="sm">Conflict</x-badge>@endif</td>
                            <td class="small">{{ $row->publicDetail($canSeeSensitive) }}</td>
                            <td class="small muted">{{ $row->unit?->code }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                @if($snapshot->exceptions()->count() > 8)<div class="card-footer small"><a href="{{ route('accountability.index', ['group' => 'unresolved']) }}">View all {{ $snapshot->exceptions()->count() }} exceptions</a></div>@endif
                @endif
            </div>

            <div class="grid grid-2">
                <div class="card">
                    <div class="card-header"><h3><x-icon name="depart"/> Departures due</h3><span class="hint">today &amp; pending</span></div>
                    @forelse($departuresDue as $m)
                        @if($loop->first)<ul class="list">@endif
                        <li>
                            <div class="avatar sm">{{ $m->personnel->initials }}</div>
                            <div class="grow"><div class="t">{{ $m->personnel->rank_name }}</div>
                            <div class="s">{{ $m->sourceLabel() }} · {{ $m->expected_departure_at->format('d M H:i') }}@if($m->expected_departure_at->isPast()) <span style="color:var(--amber)">· not yet recorded</span>@endif</div></div>
                            @if(auth()->user()->hasPermission('movements.record'))<a class="btn btn-secondary btn-xs" href="{{ route('movements.show', $m) }}">Record</a>@endif
                        </li>
                        @if($loop->last)</ul>@endif
                    @empty
                        <x-empty icon="calendar" title="No departures due" />
                    @endforelse
                </div>
                <div class="card">
                    <div class="card-header"><h3><x-icon name="arrive"/> Returns due today</h3></div>
                    @forelse($returnsDue as $m)
                        @if($loop->first)<ul class="list">@endif
                        <li>
                            <div class="avatar sm">{{ $m->personnel->initials }}</div>
                            <div class="grow"><div class="t">{{ $m->personnel->rank_name }}</div>
                            <div class="s">{{ $m->sourceLabel() }} · due {{ $m->expected_return_at->format('H:i') }}</div></div>
                            @if($m->isOverdue())<x-badge tone="red" class="sm">Overdue</x-badge>@endif
                        </li>
                        @if($loop->last)</ul>@endif
                    @empty
                        <x-empty icon="arrive" title="No returns due today" />
                    @endforelse
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header"><h3><x-icon name="file"/> Applications</h3><a class="btn btn-ghost btn-sm" href="{{ route('applications.index', ['scope' => 'all']) }}">View</a></div>
                <div class="card-body">
                    <div class="grid grid-2" style="gap:12px">
                        <x-kpi label="Pending" :value="$pendingApplications" tone="amber" :href="route('applications.index', ['scope' => 'all', 'status' => 'pending'])"/>
                        <x-kpi label="Returned" :value="$returnedApplications" tone="rose" :href="route('applications.index', ['scope' => 'all', 'status' => 'returned'])"/>
                    </div>
                    @if($openCases)
                        <a href="{{ route('verification.index') }}" class="alert alert-error mt-3 mb-0" style="text-decoration:none"><x-icon name="alert"/><div><strong>{{ $openCases }} open verification {{ \Illuminate\Support\Str::plural('case', $openCases) }}</strong><br><span class="small">Record verification attempts and resolve.</span></div></a>
                    @endif
                </div>
            </div>

            @isset($queue)
            <div class="card">
                <div class="card-header"><h3><x-icon name="inbox"/> Awaiting my action <span class="badge tone-amber sm">{{ $queueCount }}</span></h3><a class="btn btn-ghost btn-sm" href="{{ route('approvals.index') }}">Queue</a></div>
                @forelse($queue as $a)
                    @if($loop->first)<ul class="list">@endif
                    <li><div class="grow"><div class="t"><a href="{{ route('applications.show', $a) }}">{{ $a->reference_number }}</a> · {{ $a->type->is_sensitive ? 'Restricted' : $a->type->name }}</div>
                        <div class="s">{{ $a->personnel->rank_name }} · {{ $a->start_at->format('d M') }}–{{ $a->end_at->format('d M') }}</div></div>
                        @if($a->is_late || $a->is_retrospective)<x-badge tone="orange" class="sm">Late</x-badge>@endif
                    </li>
                    @if($loop->last)</ul>@endif
                @empty
                    <x-empty icon="check-circle" title="Queue is clear"/>
                @endforelse
            </div>
            @endisset

            <div class="card">
                <div class="card-header"><h3><x-icon name="activity"/> Recent movements</h3></div>
                @forelse($recentMovements as $m)
                    @if($loop->first)<ul class="list">@endif
                    <li>
                        <span class="badge sm tone-{{ $m->actual_return_at ? 'green' : 'blue' }} square">{{ $m->actual_return_at ? 'IN' : 'OUT' }}</span>
                        <div class="grow"><div class="t">{{ $m->personnel->rank_name }}</div><div class="s">{{ $m->sourceLabel() }} · {{ ($m->actual_return_at ?? $m->actual_departure_at)->format('d M H:i') }}</div></div>
                    </li>
                    @if($loop->last)</ul>@endif
                @empty
                    <x-empty title="No movements recorded"/>
                @endforelse
            </div>
        </div>
    </div>
@endisset

@if(!isset($snapshot))
    <div class="grid grid-main">
        <div class="stack">
            @include('dashboard.partials.my-status')
            <div class="card">
                <div class="card-header"><h3><x-icon name="file"/> My recent applications</h3><a href="{{ route('applications.index') }}" class="btn btn-ghost btn-sm">View all</a></div>
                @include('dashboard.partials.my-applications')
            </div>
        </div>
        <div class="stack">
            @isset($queue)
            <div class="card">
                <div class="card-header"><h3><x-icon name="inbox"/> Awaiting my action <span class="badge tone-amber sm">{{ $queueCount }}</span></h3></div>
                @forelse($queue as $a)
                    @if($loop->first)<ul class="list">@endif
                    <li><div class="grow"><div class="t"><a href="{{ route('applications.show', $a) }}">{{ $a->reference_number }}</a></div><div class="s">{{ $a->personnel->rank_name }}</div></div></li>
                    @if($loop->last)</ul>@endif
                @empty
                    <x-empty icon="check-circle" title="Queue is clear"/>
                @endforelse
            </div>
            @endisset
            <div class="card"><div class="card-header"><h3><x-icon name="info"/> How it works</h3></div>
                <div class="card-body small">
                    <ol style="margin:0;padding-left:18px;display:grid;gap:8px">
                        <li>Prepare an application and attach the required documents.</li>
                        <li>Submit — it is routed to your reviewer, then to the approving authority.</li>
                        <li>Once approved, report to the Duty Office Watch when you depart and when you return. Approval alone does not record your departure.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@elseif($user->personnel_id)
    <div class="grid grid-2 mt-3">
        @include('dashboard.partials.my-status')
        <div class="card">
            <div class="card-header"><h3><x-icon name="file"/> My recent applications</h3><a href="{{ route('applications.index', ['scope' => 'mine']) }}" class="btn btn-ghost btn-sm">View all</a></div>
            @include('dashboard.partials.my-applications')
        </div>
    </div>
@endif
@endsection
