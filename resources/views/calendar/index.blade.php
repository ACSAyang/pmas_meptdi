@extends('layouts.app')
@section('title', 'Availability Calendar')
@section('crumb', 'Duty Office Watch')
@section('content')
<div class="page-head">
    <div><h2>Availability calendar</h2><div class="sub">Approved and actual absences by day. Use it to anticipate manning before approving new requests.</div></div>
    <div class="page-actions">
        <form method="GET" class="row" style="gap:8px">
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            <select name="unit" class="select" data-autosubmit style="width:auto"><option value="">All units</option>@foreach($units as $u)<option value="{{ $u->id }}" @selected($unit?->id === $u->id)>{{ $u->name }}</option>@endforeach</select>
        </form>
        <div class="pill-nav">
            <a href="{{ route('calendar', ['month' => $month->copy()->subMonth()->format('Y-m'), 'unit' => $unit?->id]) }}"><x-icon name="chevron-left" size="sm"/></a>
            <a href="{{ route('calendar', ['unit' => $unit?->id]) }}" class="{{ $month->isSameMonth(now()) ? 'active' : '' }}">Today</a>
            <a href="{{ route('calendar', ['month' => $month->copy()->addMonth()->format('Y-m'), 'unit' => $unit?->id]) }}"><x-icon name="chevron-right" size="sm"/></a>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-header"><h3><x-icon name="calendar"/> {{ $month->format('F Y') }}</h3>
        <div class="row xs muted" style="gap:12px">
            @foreach(\App\Enums\AccountabilityCategory::awayCategories() as $c)<span class="row" style="gap:5px"><span class="sw fill-{{ $c->tone() }}" style="width:9px;height:9px;border-radius:3px;display:inline-block"></span>{{ $c->shortLabel() }}</span>@endforeach
        </div>
    </div>
    <div class="table-wrap">
    <div class="cal" style="min-width:760px">
        @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)<div class="dow">{{ $dow }}</div>@endforeach
        @foreach($days as $day)
            @php $date = $day['date']; $items = $day['items']; @endphp
            <div class="day {{ $date->month !== $month->month ? 'other' : '' }} {{ $date->isToday() ? 'today' : '' }}">
                <div class="num"><span>{{ $date->day }}</span>@if($items->count())<span class="away" title="Members away or scheduled away">{{ $items->count() }} away</span>@endif</div>
                @foreach($items->take(3) as $m)
                    @php $restricted = $m->application?->type?->is_sensitive && !$canMed; $tone = $m->isOverdue() ? 'red' : $m->category->tone(); @endphp
                    <a class="ev tone-{{ $tone }}" href="{{ route('movements.show', $m) }}" title="{{ $m->personnel->rank_name }} — {{ $restricted ? 'Restricted' : $m->category->label() }}{{ $m->actual_departure_at ? '' : ' (planned)' }}" style="{{ $m->actual_departure_at ? '' : 'border-style:dashed' }}">{{ $m->personnel->rank?->abbreviation }} {{ $m->personnel->last_name }}</a>
                @endforeach
                @if($items->count() > 3)<span class="more">+{{ $items->count() - 3 }} more</span>@endif
            </div>
        @endforeach
    </div>
    </div>
    <div class="card-footer xs muted">Solid entries: departure recorded. Dashed entries: approved/planned, departure not yet recorded. Red: overdue.</div>
</div>
@endsection
