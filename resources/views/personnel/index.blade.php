@extends('layouts.app')
@section('title', 'Personnel Masterlist')
@section('crumb', 'Personnel')
@section('content')
<div class="page-head">
    <div><h2>Personnel Masterlist</h2><div class="sub">Authoritative directory of military personnel and their assignment history.</div></div>
    <div class="page-actions">
        <a href="{{ route('personnel.export', request()->query()) }}" class="btn btn-secondary"><x-icon name="download" size="sm"/> Export CSV</a>
        @can('personnel.import')<a href="{{ route('personnel.import') }}" class="btn btn-secondary"><x-icon name="upload" size="sm"/> Import</a>@endcan
        @can('personnel.manage')<a href="{{ route('personnel.create') }}" class="btn btn-primary"><x-icon name="user-plus" size="sm"/> Add personnel</a>@endcan
    </div>
</div>

<div class="grid grid-3 mb-3">
    <x-kpi label="Assigned today" :value="$totals['assigned']" icon="users" tone="navy" meta="With an assignment covering today"/>
    <x-kpi label="Officers" :value="$totals['officers']" icon="award" tone="indigo"/>
    <x-kpi label="Enlisted" :value="$totals['assigned'] - $totals['officers']" icon="shield" tone="teal"/>
</div>

<div class="card">
    <form method="GET" class="filters">
        <div class="field grow"><label>Search</label><div class="search-wrap"><x-icon name="search" size="sm"/><input name="q" class="input" value="{{ $filters['q'] ?? '' }}" placeholder="Name, service number or designation"></div></div>
        <div class="field"><label>Unit / section</label><select name="unit" class="select" data-autosubmit><option value="">All units</option>@foreach($units as $u)<option value="{{ $u->id }}" @selected(($filters['unit'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
        <div class="field"><label>Rank</label><select name="rank" class="select" data-autosubmit><option value="">All ranks</option>@foreach($ranks as $r)<option value="{{ $r->id }}" @selected(($filters['rank'] ?? '') == $r->id)>{{ $r->abbreviation }} – {{ $r->name }}</option>@endforeach</select></div>
        <div class="field"><label>Service status</label><select name="status" class="select" data-autosubmit>
            <option value="active" @selected(($filters['status'] ?? 'active') === 'active')>Active</option>
            @foreach(\App\Models\Personnel::STATUSES as $k => $v)@if($k !== 'active')<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $v }}</option>@endif @endforeach
            <option value="all" @selected(($filters['status'] ?? '') === 'all')>All statuses</option></select></div>
        <div class="field"><button class="btn btn-primary"><x-icon name="filter" size="sm"/> Apply</button></div>
    </form>
    @if($personnel->isEmpty())
        <x-empty icon="users" title="No personnel found" text="Adjust the filters or add a member to the masterlist."/>
    @else
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Member</th><th>Rank</th><th>Unit / section</th><th>Designation</th><th>Assignment</th><th>Status</th><th>Account</th></tr></thead>
        <tbody>
        @foreach($personnel as $p)
            <tr data-href="{{ route('personnel.show', $p) }}">
                <td><x-person :p="$p"/></td>
                <td><span class="strong">{{ $p->rank?->abbreviation }}</span><div class="xs muted">{{ $p->rank?->name }}</div></td>
                <td>{{ $p->currentAssignment?->unit?->name ?? '—' }}</td>
                <td class="small">{{ $p->designation ?? '—' }}</td>
                <td class="small">@if($p->currentAssignment){{ \App\Models\PersonnelAssignment::TYPES[$p->currentAssignment->assignment_type] ?? '' }}<div class="xs muted">since {{ $p->currentAssignment->start_date->format('d M Y') }}</div>@else<span class="muted">None</span>@endif</td>
                <td><x-badge :tone="$p->service_status === 'active' ? 'green' : 'gray'">{{ \App\Models\Personnel::STATUSES[$p->service_status] ?? $p->service_status }}</x-badge></td>
                <td>@if($p->user)<x-badge tone="navy" class="sm">{{ $p->user->username }}</x-badge>@else<span class="xs muted">—</span>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    {{ $personnel->links() }}
    @endif
</div>
@endsection
