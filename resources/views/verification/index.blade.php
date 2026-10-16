@extends('layouts.app')
@section('title', 'Verification Cases')
@section('crumb', 'Duty Office Watch')
@section('content')
<div class="page-head"><div><h2>Verification cases</h2><div class="sub">Overdue, unverified and unaccounted members are resolved only with a reliable, recorded update. Overdue status alone is not proof of misconduct.</div></div></div>
<div class="card">
    <nav class="tabs">
        <a href="{{ route('verification.index', ['status' => 'open']) }}" class="{{ $status === 'open' ? 'active' : '' }}">Open <span class="count">{{ $openCount }}</span></a>
        <a href="{{ route('verification.index', ['status' => 'resolved']) }}" class="{{ $status === 'resolved' ? 'active' : '' }}">Resolved</a>
        <a href="{{ route('verification.index', ['status' => 'all']) }}" class="{{ $status === 'all' ? 'active' : '' }}">All</a>
    </nav>
    @if($cases->isEmpty())
        <x-empty icon="check-circle" title="No {{ $status === 'all' ? '' : $status }} cases" text="Cases open automatically for overdue returns, or from the accountability board."/>
    @else
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Case</th><th>Member</th><th>Reason</th><th>Related movement</th><th>Actions logged</th><th>Opened</th><th>Status</th></tr></thead>
        <tbody>@foreach($cases as $c)
            <tr data-href="{{ route('verification.show', $c) }}">
                <td class="mono strong">#{{ $c->id }}</td>
                <td><x-person :p="$c->personnel" :link="false"/></td>
                <td><x-badge :tone="$c->reason === 'overdue' ? 'red' : ($c->reason === 'unaccounted' ? 'rose' : 'amber')">{{ $c->reasonLabel() }}</x-badge></td>
                <td class="small">@if($c->movement){{ $c->movement->sourceLabel() }}<div class="xs muted">due {{ $c->movement->expected_return_at->format('d M H:i') }}</div>@else—@endif</td>
                <td class="small">{{ $c->actions->count() }}</td>
                <td class="small nowrap">{{ $c->opened_at->format('d M H:i') }}<div class="xs muted">{{ $c->opened_at->diffForHumans() }}</div></td>
                <td>@if($c->status === 'open')<x-badge tone="red" dot>Open</x-badge>@else<x-badge tone="green">Resolved</x-badge><div class="xs muted">{{ $c->resolved_at?->format('d M H:i') }}</div>@endif</td>
            </tr>
        @endforeach</tbody>
    </table></div>
    {{ $cases->links() }}
    @endif
</div>
@endsection
