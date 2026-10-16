@extends('layouts.app')
@section('title', 'Daily Strength Report')
@section('crumb', 'Reports')
@section('content')
@php use App\Support\Settings; @endphp
<div class="page-head no-print">
    <div><h2>Daily personnel strength &amp; accountability</h2><div class="sub">Calculated from assignments, movements and verified musters at the selected reporting time.</div></div>
    <div class="page-actions">
        <a href="{{ route('reports.strength', request()->query() + ['format' => 'csv']) }}" class="btn btn-secondary"><x-icon name="download" size="sm"/> Excel (CSV)</a>
        <button class="btn btn-secondary" data-print><x-icon name="printer" size="sm"/> Print / PDF</button>
        @can('reports.generate')<button class="btn btn-primary" data-dialog-open="snapshot-dialog"><x-icon name="archive" size="sm"/> Save snapshot</button>@endcan
    </div>
</div>
<div class="card mb-3 no-print">
    <form method="GET" class="filters" style="border-bottom:0;border-radius:var(--radius)">
        <div class="field"><label>Reporting date &amp; time</label><input type="datetime-local" name="as_of" class="input" value="{{ $live ? '' : $asOf->format('Y-m-d\TH:i') }}" max="{{ now()->format('Y-m-d\TH:i') }}"></div>
        <div class="field"><label>Unit / section</label><select name="unit" class="select"><option value="">All units / sections</option>@foreach($units as $u)<option value="{{ $u->id }}" @selected($unit?->id === $u->id)>{{ $u->name }}</option>@endforeach</select></div>
        <div class="field"><button class="btn btn-primary"><x-icon name="refresh" size="sm"/> Generate</button></div>
        <div class="field small muted" style="align-self:center">Leave the time empty for the current (live) figures.</div>
    </form>
</div>

<div class="report-sheet">
    <div class="report-header">
        <div class="org-sub">{{ Settings::get('parent_org') }}</div>
        <div class="org">{{ Settings::get('org_name') }}</div>
        <div class="title">Daily Personnel Strength &amp; Accountability Report</div>
    </div>
    <div class="report-meta">
        <span>As of: <b>{{ $asOf->format('d F Y, H:i') }}H</b> ({{ config('app.timezone') }})</span>
        <span>Coverage: <b>{{ $unit?->name ?? 'All units / sections' }}</b></span>
        <span>@if($live)<span class="watermark-live tone-green badge">LIVE CALCULATION — NOT A SAVED SNAPSHOT</span>@else<span class="watermark-live tone-amber badge">POINT-IN-TIME CALCULATION</span>@endif</span>
    </div>
    @include('reports.partials.strength-body', [
        'summary' => $snapshot->summaryArray(),
        'byUnit' => $snapshot->byUnit(),
        'rows' => $snapshot->rows->map(fn ($r) => $r->toArray($canMed)),
    ])
    <div class="signatures">
        <div class="sig">{{ Settings::get('prepared_by_label') }}:<div class="line"></div>{{ auth()->user()->name }}<div class="xs muted">Duty Office Watch</div></div>
        <div class="sig">{{ Settings::get('verified_by_label') }}:<div class="line"></div>&nbsp;</div>
        <div class="sig">{{ Settings::get('noted_by_label') }}:<div class="line"></div>&nbsp;</div>
    </div>
    <div class="report-foot"><span>{{ Settings::get('report_footer_note') }}</span><span>Generated {{ now()->format('d M Y H:i') }} by {{ auth()->user()->name }} · PMAS</span></div>
</div>
@endsection
@push('dialogs')
<dialog class="modal" id="snapshot-dialog"><form method="POST" action="{{ route('reports.snapshot', ['as_of' => $live ? null : $asOf->format('Y-m-d H:i'), 'unit' => $unit?->id]) }}">@csrf
    <div class="modal-head"><x-icon name="archive"/><h3>Save report snapshot</h3><button class="x" type="button" data-dialog-close><x-icon name="x"/></button></div>
    <div class="modal-body stack">
        <p class="small mb-0">The figures as of <b>{{ $asOf->format('d M Y H:i') }}</b> for <b>{{ $unit?->name ?? 'all units' }}</b> will be preserved exactly as generated, with your name and the generation time. Later corrections to records will not change the saved snapshot.</p>
        <x-field label="Remarks (optional)"><textarea name="remarks" class="textarea" rows="2"></textarea></x-field>
    </div>
    <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Cancel</button><button class="btn btn-primary">Save snapshot</button></div>
</form></dialog>
@endpush
