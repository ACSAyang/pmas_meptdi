@extends('layouts.app')
@section('title', 'Import Preview')
@section('crumb', 'Personnel Masterlist')
@section('content')
<div class="page-head"><div><h2>Review import</h2><div class="sub">Only valid rows will be imported. Fix invalid rows in the file and upload again if needed.</div></div>
    <div class="page-actions">
        <a href="{{ route('personnel.import') }}" class="btn btn-secondary">Start over</a>
        <form method="POST" action="{{ route('personnel.import.store') }}" data-confirm="Import {{ $valid }} valid row(s) into the masterlist?">@csrf<input type="hidden" name="token" value="{{ $token }}">
            <button class="btn btn-primary" @disabled($valid === 0)><x-icon name="check" size="sm"/> Import {{ $valid }} row(s)</button></form>
    </div></div>
<div class="grid grid-3 mb-3">
    <x-kpi label="Rows read" :value="count($rows)" tone="navy"/>
    <x-kpi label="Valid" :value="$valid" tone="green"/>
    <x-kpi label="With errors" :value="$invalid" tone="red" :alert="$invalid > 0"/>
</div>
<div class="card"><div class="table-wrap"><table class="table compact">
    <thead><tr><th>Line</th><th>Service No.</th><th>Rank</th><th>Name</th><th>Unit</th><th>Type</th><th>Result</th></tr></thead>
    <tbody>@foreach($rows as $r)
        <tr>
            <td class="muted">{{ $r['line'] }}</td><td class="mono">{{ $r['data']['service_number'] ?? '' }}</td><td>{{ $r['data']['rank'] ?? '' }}</td>
            <td>{{ $r['data']['last_name'] ?? '' }}, {{ $r['data']['first_name'] ?? '' }}</td><td>{{ $r['data']['unit_code'] ?? '' }}</td><td>{{ $r['data']['assignment_type'] }}</td>
            <td>@if($r['errors'])<span class="error-text">{{ implode('; ', $r['errors']) }}</span>@else<x-badge tone="green" class="sm">Ready</x-badge>@endif</td>
        </tr>
    @endforeach</tbody>
</table></div></div>
@endsection
