@extends('layouts.app')
@section('title', 'Audit Trail')
@section('crumb', 'Administration')
@section('content')
<div class="page-head"><div><h2>Audit trail</h2><div class="sub">Append-only record of sign-ins, approvals, movements, corrections and administrative changes.</div></div>
    <div class="page-actions"><a href="{{ route('admin.audit.index', request()->query() + ['format' => 'csv']) }}" class="btn btn-secondary"><x-icon name="download" size="sm"/> Export CSV</a></div></div>
<div class="card">
    <form method="GET" class="filters">
        <div class="field grow"><label>Search</label><input name="q" class="input" value="{{ $filters['q'] ?? '' }}" placeholder="Action, description or reason"></div>
        <div class="field"><label>User</label><select name="user" class="select"><option value="">Anyone</option>@foreach($users as $usr)<option value="{{ $usr->id }}" @selected(($filters['user'] ?? '') == $usr->id)>{{ $usr->name }}</option>@endforeach</select></div>
        <div class="field"><label>Record type</label><select name="entity" class="select"><option value="">Any</option>@foreach($entities as $e)<option value="{{ $e }}" @selected(($filters['entity'] ?? '') === $e)>{{ \Illuminate\Support\Str::headline($e) }}</option>@endforeach</select></div>
        <div class="field"><label>From</label><input type="date" name="from" class="input" value="{{ $filters['from'] ?? '' }}"></div>
        <div class="field"><label>To</label><input type="date" name="to" class="input" value="{{ $filters['to'] ?? '' }}"></div>
        <div class="field"><button class="btn btn-primary">Apply</button></div>
    </form>
    <div class="table-wrap"><table class="table compact">
        <thead><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Record</th><th>Description</th><th>Reason</th><th>IP</th></tr></thead>
        <tbody>@foreach($logs as $l)
            <tr data-href="{{ route('admin.audit.show', $l) }}">
                <td class="nowrap tabular small">{{ $l->created_at->format('d M Y H:i:s') }}</td>
                <td class="small">{{ $l->user?->name ?? 'System' }}</td>
                <td><x-badge :tone="$l->actionTone()" class="sm">{{ $l->action }}</x-badge></td>
                <td class="small">{{ $l->entity_type ? \Illuminate\Support\Str::headline($l->entity_type).' #'.$l->entity_id : '—' }}</td>
                <td class="small">{{ \Illuminate\Support\Str::limit($l->description, 80) }}</td>
                <td class="small">{{ \Illuminate\Support\Str::limit($l->reason, 50) }}</td>
                <td class="xs muted mono">{{ $l->ip_address }}</td>
            </tr>
        @endforeach</tbody>
    </table></div>
    {{ $logs->links() }}
</div>
@endsection
