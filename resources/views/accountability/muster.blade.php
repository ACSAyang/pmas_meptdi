@extends('layouts.app')
@section('title', 'Muster / Roll Call')
@section('crumb', 'Duty Office Watch')
@section('content')
@php use App\Enums\AccountabilityCategory as Cat; @endphp
<div class="page-head">
    <div><h2>Muster / roll call</h2><div class="sub">Confirm members who are physically present, on sick call, or not located. Members recorded as away are handled through their movement record.</div></div>
    <div class="page-actions"><a href="{{ route('accountability.index', ['unit' => $unit?->id]) }}" class="btn btn-secondary"><x-icon name="shield" size="sm"/> Accountability board</a></div>
</div>

<div class="grid grid-main">
<form method="POST" action="{{ route('muster.record') }}" class="card">@csrf
    <div class="filters" style="align-items:center">
        <div class="field"><label>Unit / section</label>
            <select class="select" onchange="window.location='{{ route('muster.index') }}?unit='+this.value"><option value="">All units</option>@foreach($units as $x)<option value="{{ $x->id }}" @selected($unit?->id === $x->id)>{{ $x->name }}</option>@endforeach</select></div>
        <div class="field grow"><label>Quick filter</label><div class="search-wrap"><x-icon name="search" size="sm"/><input class="input" data-quickfilter="#muster-table" placeholder="Type a name or service number" value="{{ request('q') }}"></div></div>
        <div class="field"><label>Muster time</label><input type="datetime-local" name="occurred_at" class="input" value="{{ now()->format('Y-m-d\TH:i') }}" max="{{ now()->addMinutes(5)->format('Y-m-d\TH:i') }}" required></div>
    </div>
    <div class="table-wrap"><table class="table compact" id="muster-table">
        <thead><tr><th class="sel"><input type="checkbox" class="checkbox" data-select-all="muster" aria-label="Select all"></th><th>Member</th><th>Unit</th><th>Current status</th><th>Basis</th></tr></thead>
        <tbody>
        @foreach($rows as $row)
            @php $away = $row->movement && $row->movement->status->value === 'departed'; @endphp
            <tr>
                <td class="sel"><input type="checkbox" class="checkbox" name="personnel_ids[]" value="{{ $row->personnel->id }}" data-select="muster" @disabled($away) aria-label="Select {{ $row->personnel->full_name }}"></td>
                <td><x-person :p="$row->personnel" :link="false"/></td>
                <td class="small">{{ $row->unit?->code }}</td>
                <td><x-category-badge :category="$row->category"/></td>
                <td class="small muted">{{ $away ? 'Recorded away — record return on the movement' : $row->publicDetail($canMed) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="card-footer">
        <div class="row between">
            <div class="small"><b data-select-count="muster">0</b> selected</div>
            <div class="row" style="gap:8px">
                <input name="remarks" class="input input-sm" placeholder="Remarks (optional)" style="width:220px" maxlength="255">
                <button class="btn btn-outline-danger" name="event_type" value="absent" data-requires-selection="muster" disabled><x-icon name="x" size="sm"/> Not located</button>
                <button class="btn btn-secondary" name="event_type" value="sick_call" data-requires-selection="muster" disabled><x-icon name="stethoscope" size="sm"/> Sick call</button>
                <button class="btn btn-success" name="event_type" value="present" data-requires-selection="muster" disabled><x-icon name="check" size="sm"/> Mark present</button>
            </div>
        </div>
    </div>
</form>

<div class="stack">
    <div class="card">
        <div class="card-header"><h3><x-icon name="chart"/> Current picture</h3><span class="hint">{{ $unit?->code ?? 'All' }}</span></div>
        <div class="card-body">
            <div class="grid grid-2" style="gap:10px">
                <x-kpi label="Assigned" :value="$snapshot->assigned()" tone="navy"/>
                <x-kpi label="Present" :value="$snapshot->present()" tone="green"/>
                <x-kpi label="Away (auth.)" :value="$snapshot->authorizedAway()" tone="blue"/>
                <x-kpi label="Unresolved" :value="$snapshot->unresolved()" tone="red" :alert="$snapshot->unresolved() > 0"/>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3><x-icon name="activity"/> Recent entries</h3></div>
        @forelse($recent as $e)
            @if($loop->first)<ul class="list">@endif
            <li><x-badge :tone="$e->event_type === 'sick_call' && !$canMed ? 'violet' : $e->tone()" class="sm">{{ $e->event_type === 'sick_call' && !$canMed ? 'Medical' : $e->label() }}</x-badge>
                <div class="grow"><div class="t small">{{ $e->personnel->rank_name }}</div><div class="s xs">{{ $e->occurred_at->format('d M H:i') }} · {{ $e->recorder->name }}</div></div></li>
            @if($loop->last)</ul>@endif
        @empty
            <x-empty icon="muster" title="No entries yet"/>
        @endforelse
    </div>
</div>
</div>
@endsection
