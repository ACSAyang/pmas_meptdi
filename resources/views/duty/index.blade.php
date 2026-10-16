@extends('layouts.app')
@section('title', 'Training & Duty')
@section('crumb', 'Personnel')
@section('content')
<div class="page-head"><div><h2>Training &amp; duty assignments</h2><div class="sub">Training, schooling, official business, temporary duty and other authorized assignments — integrated with accountability without double counting.</div></div>
    <div class="page-actions">@can('duty.manage')<a href="{{ route('duty.create') }}" class="btn btn-primary"><x-icon name="plus" size="sm"/> New assignment</a>@endcan</div></div>
<div class="card">
    <nav class="tabs">
        @foreach(['current' => 'Scheduled & ongoing', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $v)
            <a href="{{ route('duty.index', ['status' => $k] + $filters) }}" class="{{ $status === $k ? 'active' : '' }}">{{ $v }}</a>
        @endforeach
    </nav>
    <form method="GET" class="filters"><input type="hidden" name="status" value="{{ $status }}">
        <div class="field grow"><label>Search</label><input name="q" class="input" value="{{ $filters['q'] ?? '' }}" placeholder="Title or member"></div>
        <div class="field"><label>Type</label><select name="type" class="select" data-autosubmit><option value="">All types</option>@foreach(\App\Enums\DutyType::cases() as $t)<option value="{{ $t->value }}" @selected(($filters['type'] ?? '') === $t->value)>{{ $t->label() }}</option>@endforeach</select></div>
        <div class="field"><button class="btn btn-primary">Apply</button></div>
    </form>
    @if($duties->isEmpty())
        <x-empty icon="briefcase" title="No assignments"/>
    @else
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Member</th><th>Assignment</th><th>Type</th><th>Period</th><th>Away?</th><th>Departure</th><th>Status</th></tr></thead>
        <tbody>@foreach($duties as $d)
            <tr data-href="{{ route('duty.show', $d) }}">
                <td><x-person :p="$d->personnel" :link="false"/></td>
                <td><span class="strong">{{ $d->title }}</span><div class="xs muted">{{ $d->location }} @if($d->reference_number)· <span class="mono">{{ $d->reference_number }}</span>@endif</div></td>
                <td><x-badge :tone="$d->assignment_type->category()->tone()">{{ $d->assignment_type->label() }}</x-badge></td>
                <td class="small nowrap">{{ $d->start_at->format('d M Y') }} – {{ $d->end_at->format('d M Y') }}</td>
                <td class="small">{{ $d->is_away ? 'Away' : 'In-house' }}</td>
                <td class="small">@if($d->movement){{ $d->movement->actual_departure_at ? 'Departed '.$d->movement->actual_departure_at->format('d M') : 'Not yet recorded' }}@else—@endif</td>
                <td><x-badge :tone="$d->statusTone()">{{ \App\Models\DutyAssignment::STATUSES[$d->status] }}</x-badge></td>
            </tr>
        @endforeach</tbody>
    </table></div>
    {{ $duties->links() }}
    @endif
</div>
@endsection
