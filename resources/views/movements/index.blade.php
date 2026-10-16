@extends('layouts.app')
@section('title', 'Departures & Returns')
@section('crumb', 'Duty Office Watch')
@section('content')
<div class="page-head">
    <div><h2>Departures &amp; returns</h2><div class="sub">Actual movements are recorded separately from approvals. An approved application does not mean the member has departed.</div></div>
    <div class="page-actions">
        <a href="{{ route('movements.index', request()->query() + ['format' => 'csv']) }}" class="btn btn-secondary"><x-icon name="download" size="sm"/> Export</a>
        @can('movements.record')<a href="{{ route('movements.create') }}" class="btn btn-primary"><x-icon name="plus" size="sm"/> Direct movement</a>@endcan
    </div>
</div>
<div class="card">
    <nav class="tabs">
        <a href="{{ route('movements.index', ['tab' => 'due'] + $filters) }}" class="{{ $tab === 'due' ? 'active' : '' }}"><x-icon name="depart" size="sm"/> Due to depart <span class="count">{{ $counts['due'] }}</span></a>
        <a href="{{ route('movements.index', ['tab' => 'away'] + $filters) }}" class="{{ $tab === 'away' ? 'active' : '' }}"><x-icon name="move" size="sm"/> Away now <span class="count">{{ $counts['away'] }}</span></a>
        <a href="{{ route('movements.index', ['tab' => 'returning'] + $filters) }}" class="{{ $tab === 'returning' ? 'active' : '' }}"><x-icon name="arrive" size="sm"/> Due back today <span class="count">{{ $counts['returning'] }}</span></a>
        <a href="{{ route('movements.index', ['tab' => 'overdue'] + $filters) }}" class="{{ $tab === 'overdue' ? 'active' : '' }}" @if($counts['overdue']) style="color:var(--red)" @endif><x-icon name="alert" size="sm"/> Overdue <span class="count" @if($counts['overdue']) style="background:var(--red-bg);color:var(--red)" @endif>{{ $counts['overdue'] }}</span></a>
        <a href="{{ route('movements.index', ['tab' => 'history'] + $filters) }}" class="{{ $tab === 'history' ? 'active' : '' }}"><x-icon name="list" size="sm"/> All / history</a>
    </nav>
    <form method="GET" class="filters">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="field grow"><label>Search member</label><div class="search-wrap"><x-icon name="search" size="sm"/><input name="q" class="input" value="{{ $filters['q'] ?? '' }}" placeholder="Name or service number"></div></div>
        <div class="field"><label>Unit</label><select name="unit" class="select" data-autosubmit><option value="">All units</option>@foreach($units as $u)<option value="{{ $u->id }}" @selected(($filters['unit'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
        @if($tab === 'history')
            <div class="field"><label>Status</label><select name="status" class="select" data-autosubmit><option value="">Any</option>@foreach(\App\Enums\MovementStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->shortLabel() }}</option>@endforeach</select></div>
            <div class="field"><label>Category</label><select name="category" class="select" data-autosubmit><option value="">Any</option>@foreach(\App\Enums\AccountabilityCategory::awayCategories() as $c)<option value="{{ $c->value }}" @selected(($filters['category'] ?? '') === $c->value)>{{ $c->label() }}</option>@endforeach</select></div>
            <div class="field"><label>From</label><input type="date" name="from" class="input" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="field"><label>To</label><input type="date" name="to" class="input" value="{{ $filters['to'] ?? '' }}"></div>
        @endif
        <div class="field"><button class="btn btn-primary"><x-icon name="filter" size="sm"/> Apply</button></div>
    </form>
    @include('movements.partials.table', ['movements' => $movements, 'actions' => true, 'emptyText' => [
        'due' => 'No approved movements are due to depart today or tomorrow.',
        'away' => 'No members are recorded as away.',
        'returning' => 'No returns are due today.',
        'overdue' => 'No overdue returns. All away members are within their authorized period.',
    ][$tab] ?? null])
</div>
@endsection
@push('dialogs')@include('movements.partials.dialogs')@endpush
