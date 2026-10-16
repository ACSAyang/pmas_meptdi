@extends('layouts.app')
@section('title', 'Reports')
@section('crumb', 'Reports')
@section('content')
<div class="page-head"><div><h2>Reports</h2><div class="sub">Operational and formal reports. Each report shows its reporting period, generation time and filters. Save a snapshot to preserve submitted figures.</div></div>
    <div class="page-actions"><a href="{{ route('reports.snapshots') }}" class="btn btn-secondary"><x-icon name="archive" size="sm"/> Saved snapshots</a></div></div>
<div class="grid grid-3">
    @foreach($catalog as $key => [$title, $desc, $icon])
        <a href="{{ $key === 'strength' ? route('reports.strength') : route('reports.show', $key) }}" class="card" style="text-decoration:none;color:inherit;display:block;{{ $key === 'strength' ? 'border-color:var(--navy-500)' : '' }}">
            <div class="card-body">
                <div class="row" style="gap:12px;align-items:flex-start">
                    <div class="empty" style="padding:0"><div class="icon-wrap" style="margin:0;width:44px;height:44px"><x-icon :name="$icon"/></div></div>
                    <div><div class="strong" style="font-size:15px;color:var(--text)">{{ $title }}</div><div class="small muted mt-1">{{ $desc }}</div></div>
                </div>
            </div>
        </a>
    @endforeach
</div>
<div class="card mt-4">
    <div class="card-header"><h3><x-icon name="archive"/> Recently saved snapshots</h3></div>
    @forelse($recent as $r)
        @if($loop->first)<ul class="list">@endif
        <li><div class="grow"><div class="t"><a href="{{ route('reports.snapshots.show', $r) }}">{{ $r->title }}</a></div><div class="s">As of {{ $r->as_of_at?->format('d M Y H:i') }} · saved by {{ $r->generator->name }} {{ $r->generated_at->diffForHumans() }}</div></div>
            <x-badge :tone="$r->status === 'certified' ? 'green' : 'amber'">{{ $r->status === 'certified' ? 'Verified' : 'Not yet verified' }}</x-badge></li>
        @if($loop->last)</ul>@endif
    @empty
        <x-empty icon="archive" title="No snapshots saved yet" text="Open the strength report and choose “Save snapshot” to preserve the figures submitted to the chain of command."/>
    @endforelse
</div>
@endsection
