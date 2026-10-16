@extends('layouts.app')
@section('title', 'Saved Report Snapshots')
@section('crumb', 'Reports')
@section('content')
<div class="page-head"><div><h2>Saved report snapshots</h2><div class="sub">Preserved figures as submitted, with parameters, generator and verification (BR-017).</div></div></div>
<div class="card">
    @if($runs->isEmpty())
        <x-empty icon="archive" title="No snapshots yet"/>
    @else
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Report</th><th>As of</th><th>Assigned</th><th>Present</th><th>Away</th><th>Unresolved</th><th>Generated</th><th>Verification</th></tr></thead>
        <tbody>@foreach($runs as $r)
            <tr data-href="{{ route('reports.snapshots.show', $r) }}">
                <td class="strong">{{ $r->title }}</td>
                <td class="nowrap tabular">{{ $r->as_of_at?->format('d M Y H:i') }}</td>
                <td class="num">{{ $r->summary['assigned'] ?? '—' }}</td><td class="num">{{ $r->summary['present'] ?? '—' }}</td><td class="num">{{ $r->summary['authorized_away'] ?? '—' }}</td>
                <td class="num">@if(($r->summary['unresolved'] ?? 0) > 0)<span style="color:var(--red);font-weight:700">{{ $r->summary['unresolved'] }}</span>@else 0 @endif</td>
                <td class="small">{{ $r->generator->name }}<div class="xs muted">{{ $r->generated_at->format('d M Y H:i') }}</div></td>
                <td>@if($r->status === 'certified')<x-badge tone="green">Verified</x-badge><div class="xs muted">{{ $r->certifier?->name }}</div>@else<x-badge tone="amber">Pending</x-badge>@endif</td>
            </tr>
        @endforeach</tbody>
    </table></div>
    {{ $runs->links() }}
    @endif
</div>
@endsection
