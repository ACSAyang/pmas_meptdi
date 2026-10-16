@extends('layouts.app')
@section('title', $title)
@section('crumb', 'Reports')
@section('content')
@php use App\Support\Settings; $needsPeriod = in_array($type, ['schedule', 'register', 'training', 'movements']); @endphp
<div class="page-head no-print">
    <div><h2>{{ $title }}</h2><div class="sub">{{ $description }}</div></div>
    <div class="page-actions">
        <a href="{{ route('reports.show', [$type] + request()->query() + ['format' => 'csv']) }}" class="btn btn-secondary"><x-icon name="download" size="sm"/> Excel (CSV)</a>
        <button class="btn btn-primary" data-print><x-icon name="printer" size="sm"/> Print / PDF</button>
    </div>
</div>
<div class="card mb-3 no-print">
    <form method="GET" class="filters" style="border-bottom:0;border-radius:var(--radius)">
        @if($needsPeriod)
            <div class="field"><label>From</label><input type="date" name="from" class="input" value="{{ request('from', now()->startOfMonth()->toDateString()) }}"></div>
            <div class="field"><label>To</label><input type="date" name="to" class="input" value="{{ request('to', now()->toDateString()) }}"></div>
        @endif
        @if($type === 'unresolved')<div class="field"><label>As of</label><input type="datetime-local" name="as_of" class="input" value="{{ request('as_of') }}" max="{{ now()->format('Y-m-d\TH:i') }}"></div>@endif
        @if($type !== 'training' || true)<div class="field"><label>Unit</label><select name="unit" class="select"><option value="">All units</option>@foreach($units as $u)<option value="{{ $u->id }}" @selected(request('unit') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>@endif
        @if($type === 'register')
            <div class="field"><label>Type</label><select name="type" class="select"><option value="">All types</option>@foreach($types as $t)<option value="{{ $t->id }}" @selected(request('type') == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
            <div class="field"><label>Status</label><select name="status" class="select"><option value="">Any</option>@foreach(\App\Enums\ApplicationStatus::cases() as $s)@if($s->value !== 'draft')<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endif @endforeach</select></div>
        @endif
        <div class="field"><button class="btn btn-primary"><x-icon name="refresh" size="sm"/> Generate</button></div>
    </form>
</div>
<div class="report-sheet">
    <div class="report-header">
        <div class="org-sub">{{ Settings::get('parent_org') }}</div>
        <div class="org">{{ Settings::get('org_name') }}</div>
        <div class="title">{{ $title }}</div>
        <div class="small muted mt-1">{{ $subtitle }}</div>
    </div>
    <div class="report-meta">
        @foreach($params as $k => $v)<span>{{ ucfirst(str_replace('_', ' ', $k)) }}: <b>{{ $v }}</b></span>@endforeach
        <span>Records: <b>{{ count($rows) }}</b></span>
    </div>
    @if($summary)
        <div class="row mb-2" style="gap:8px">@foreach($summary as $k => $v)<span class="badge tone-navy square">{{ $k }}: <b>{{ $v }}</b></span>@endforeach</div>
    @endif
    @if(empty($rows))
        <x-empty icon="check-circle" title="No records for the selected parameters"/>
    @else
    <div class="table-wrap"><table class="report-table">
        <thead><tr><th>#</th>@foreach($columns as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
        <tbody>@foreach($rows as $i => $row)<tr><td class="num">{{ $i + 1 }}</td>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach</tbody>
    </table></div>
    @endif
    <div class="signatures">
        <div class="sig">{{ Settings::get('prepared_by_label') }}:<div class="line"></div>{{ auth()->user()->name }}</div>
        <div class="sig">{{ Settings::get('verified_by_label') }}:<div class="line"></div>&nbsp;</div>
        <div class="sig">{{ Settings::get('noted_by_label') }}:<div class="line"></div>&nbsp;</div>
    </div>
    <div class="report-foot"><span>{{ Settings::get('report_footer_note') }}</span><span>Generated {{ now()->format('d M Y H:i') }} by {{ auth()->user()->name }} · PMAS</span></div>
</div>
@endsection
