@extends('layouts.app')
@section('title', 'Accountability Board')
@section('crumb', 'Duty Office Watch')
@section('content')
@php use App\Enums\AccountabilityCategory as Cat; $u = auth()->user(); @endphp
<div class="page-head">
    <div><h2>Accountability board</h2>
        <div class="sub">Each assigned member has exactly one primary category at the selected time. Unknown status is never assumed present.</div></div>
    <div class="page-actions">
        <a href="{{ route('accountability.index', $filters + ['format' => 'csv']) }}" class="btn btn-secondary"><x-icon name="download" size="sm"/> Export</a>
        @can('reports.view')<a href="{{ route('reports.strength', ['as_of' => $live ? null : $snapshot->asOf->format('Y-m-d\TH:i'), 'unit' => $unit?->id]) }}" class="btn btn-secondary"><x-icon name="printer" size="sm"/> Strength report</a>@endcan
        @can('accountability.muster')<a href="{{ route('muster.index', ['unit' => $unit?->id]) }}" class="btn btn-primary"><x-icon name="muster" size="sm"/> Take muster</a>@endcan
    </div>
</div>

<div class="card mb-3">
    <form method="GET" class="filters">
        <div class="field"><label>Reporting time</label><input type="datetime-local" name="as_of" class="input" value="{{ $live ? '' : $snapshot->asOf->format('Y-m-d\TH:i') }}" max="{{ now()->format('Y-m-d\TH:i') }}"></div>
        <div class="field"><label>Unit / section</label><select name="unit" class="select"><option value="">All units</option>@foreach($units as $x)<option value="{{ $x->id }}" @selected($unit?->id === $x->id)>{{ $x->name }}</option>@endforeach</select></div>
        <div class="field"><label>Category</label><select name="category" class="select"><option value="">All categories</option>@foreach(Cat::cases() as $c)<option value="{{ $c->value }}" @selected(($filters['category'] ?? '') === $c->value)>{{ $c->label() }}</option>@endforeach</select></div>
        <div class="field"><label>Group</label><select name="group" class="select"><option value="">All</option><option value="unresolved" @selected(($filters['group'] ?? '') === 'unresolved')>Unresolved only</option><option value="away" @selected(($filters['group'] ?? '') === 'away')>Authorized away</option></select></div>
        <div class="field grow"><label>Search</label><input name="q" class="input" value="{{ $filters['q'] ?? '' }}" placeholder="Name or service no."></div>
        <div class="field"><button class="btn btn-primary"><x-icon name="refresh" size="sm"/> Calculate</button></div>
        @if(!$live)<div class="field"><a href="{{ route('accountability.index') }}" class="btn btn-ghost">Back to live</a></div>@endif
    </form>
    <div class="card-body">
        <div class="row between mb-2">
            @if($live)
                <span class="badge tone-green"><span class="bdot"></span> LIVE VIEW · as of {{ $snapshot->asOf->format('d M Y H:i:s') }}</span>
            @else
                <span class="badge tone-amber"><x-icon name="clock" size="sm"/> POINT-IN-TIME VIEW · {{ $snapshot->asOf->format('d M Y H:i') }} — uses assignments and movements effective at that time</span>
            @endif
            <span class="small muted">{{ $unit?->name ?? 'All units / sections' }}</span>
        </div>
        @include('partials.strength-breakdown', ['snapshot' => $snapshot, 'linkParams' => array_filter(['unit' => $unit?->id, 'as_of' => $filters['as_of'] ?? null])])
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><x-icon name="users"/> Members <span class="badge tone-navy sm">{{ $rows->count() }}</span></h3>
        @if(!empty($filters['category']) || !empty($filters['group']) || !empty($filters['q']))<a href="{{ route('accountability.index', array_filter(['unit' => $unit?->id, 'as_of' => $filters['as_of'] ?? null])) }}" class="btn btn-ghost btn-sm">Clear filters</a>@endif</div>
    @if($rows->isEmpty())
        <x-empty icon="users" title="No members match"/>
    @else
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Member</th><th>Unit</th><th>Category</th><th>Basis / details</th><th>Since</th><th></th></tr></thead>
        <tbody>
        @foreach($rows as $row)
            <tr>
                <td><x-person :p="$row->personnel"/></td>
                <td class="small">{{ $row->unit?->code }}</td>
                <td><x-category-badge :category="$row->category"/>@if($row->conflict) <x-badge tone="orange" class="sm">Conflict</x-badge>@endif</td>
                <td class="small">{{ $row->publicDetail($canMed) }}</td>
                <td class="small nowrap tabular muted">{{ $row->since?->format('d M H:i') ?? '—' }}</td>
                <td class="right nowrap">
                    @if($row->movement && $u->hasPermission('movements.view'))<a class="btn btn-ghost btn-xs" href="{{ route('movements.show', $row->movement) }}">Movement</a>@endif
                    @if($live && $row->category->isUnresolved() && $u->hasPermission('accountability.verify'))
                        <form method="POST" action="{{ route('verification.store') }}" style="display:inline">@csrf
                            <input type="hidden" name="personnel_id" value="{{ $row->personnel->id }}"><input type="hidden" name="reason" value="{{ \App\Services\VerificationService::categoryReason($row->category) }}">
                            <button class="btn btn-secondary btn-xs">Verify</button></form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</div>
@endsection
