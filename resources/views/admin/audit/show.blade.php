@extends('layouts.app')
@section('title', 'Audit Entry #'.$log->id)
@section('crumb', 'Audit Trail')
@section('content')
<div class="page-head"><div><h2>Audit entry #{{ $log->id }}</h2><div class="sub">{{ $log->created_at->format('l, d F Y H:i:s') }}</div></div><div class="page-actions"><a href="{{ url()->previous() }}" class="btn btn-secondary">Back</a></div></div>
<div class="grid grid-main">
    <div class="card"><div class="card-header"><h3>Change detail</h3></div>
        <div class="table-wrap"><table class="table compact">
            <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
            <tbody>
            @php $keys = array_unique(array_merge(array_keys($log->before_data ?? []), array_keys($log->after_data ?? []))); @endphp
            @forelse($keys as $k)
                <tr><td class="mono small strong">{{ $k }}</td>
                    <td class="mono small" style="color:var(--red)">{{ is_array($log->before_data[$k] ?? null) ? json_encode($log->before_data[$k]) : var_export($log->before_data[$k] ?? null, true) }}</td>
                    <td class="mono small" style="color:var(--green)">{{ is_array($log->after_data[$k] ?? null) ? json_encode($log->after_data[$k]) : var_export($log->after_data[$k] ?? null, true) }}</td></tr>
            @empty
                <tr><td colspan="3" class="muted small">No field-level data for this action.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    <div class="card"><div class="card-header"><h3>Entry</h3></div><div class="card-body">
        <dl class="dl" style="grid-template-columns:110px 1fr">
            <dt>Action</dt><dd><x-badge :tone="$log->actionTone()">{{ $log->action }}</x-badge></dd>
            <dt>User</dt><dd>{{ $log->user?->name ?? 'System' }}</dd>
            <dt>Record</dt><dd>{{ $log->entity_type ? \Illuminate\Support\Str::headline($log->entity_type).' #'.$log->entity_id : '—' }}</dd>
            <dt>Description</dt><dd>{{ $log->description }}</dd>
            <dt>Reason</dt><dd>{{ $log->reason ?? '—' }}</dd>
            <dt>IP address</dt><dd class="mono">{{ $log->ip_address ?? '—' }}</dd>
            <dt>User agent</dt><dd class="xs muted">{{ $log->user_agent ?? '—' }}</dd>
        </dl>
    </div></div>
</div>
@endsection
