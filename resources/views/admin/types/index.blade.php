@extends('layouts.app')
@section('title', 'Application Types')
@section('crumb', 'Administration')
@section('content')
<div class="page-head"><div><h2>Application types &amp; workflows</h2><div class="sub">Notice periods, limits, document checklists and approval chains are configurable per type. Do not set values until MEPTDI confirms the official policy.</div></div>
    <div class="page-actions"><a href="{{ route('admin.types.create') }}" class="btn btn-primary"><x-icon name="plus" size="sm"/> New type</a></div></div>
<div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Code</th><th>Type</th><th>Counts as</th><th>Notice period</th><th>Max duration</th><th>Documents</th><th>Approval chain</th><th>Status</th></tr></thead>
    <tbody>@foreach($types as $t)
        @php $wf = $t->workflows->first(); @endphp
        <tr data-href="{{ route('admin.types.edit', $t) }}">
            <td class="mono strong">{{ $t->code }}</td>
            <td><span class="strong">{{ $t->name }}</span>@if($t->is_sensitive) <x-badge tone="violet" class="sm">Restricted</x-badge>@endif</td>
            <td><x-category-badge :category="$t->category"/></td>
            <td class="small">{{ $t->lead_time_days !== null ? $t->lead_time_days.' day(s)' : 'Not configured' }}</td>
            <td class="small">{{ $t->max_duration_days ? $t->max_duration_days.' day(s)' : 'Not configured' }}</td>
            <td class="small">{{ $t->document_requirements_count }}</td>
            <td class="small">@if($wf)v{{ $wf->version }}: {{ $wf->steps->map(fn ($s) => $s->role->name)->join(' → ') }}@else<span style="color:var(--red)">None</span>@endif</td>
            <td>@if($t->is_active)<x-badge tone="green">Enabled</x-badge>@else<x-badge tone="gray">Disabled</x-badge>@endif</td>
        </tr>
    @endforeach</tbody>
</table></div></div>
@endsection
