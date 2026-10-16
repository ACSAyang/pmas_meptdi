@extends('layouts.app')
@section('title', 'Report Snapshot #'.$run->id)
@section('crumb', 'Saved Report Snapshots')
@section('content')
@php use App\Support\Settings; $s = $run->summary; @endphp
<div class="page-head no-print">
    <div><h2>Snapshot #{{ $run->id }}</h2><div class="sub">Saved {{ $run->generated_at->format('d M Y H:i') }} by {{ $run->generator->name }}. Figures are frozen and do not change when records are corrected.</div></div>
    <div class="page-actions">
        <button class="btn btn-secondary" data-print><x-icon name="printer" size="sm"/> Print / PDF</button>
        @if($run->status !== 'certified' && auth()->user()->hasPermission('reports.certify') && $run->generated_by !== auth()->id())
            <form method="POST" action="{{ route('reports.snapshots.certify', $run) }}" data-confirm="Confirm that you have reviewed this report and verify it for submission?" data-confirm-button="Verify report">@csrf<button class="btn btn-success"><x-icon name="check" size="sm"/> Verify report</button></form>
        @endif
    </div>
</div>
<div class="report-sheet">
    <div class="report-header">
        <div class="org-sub">{{ Settings::get('parent_org') }}</div>
        <div class="org">{{ Settings::get('org_name') }}</div>
        <div class="title">Daily Personnel Strength &amp; Accountability Report</div>
    </div>
    <div class="report-meta">
        <span>As of: <b>{{ $run->as_of_at->format('d F Y, H:i') }}H</b></span>
        <span>Coverage: <b>{{ $run->parameters['unit'] ?? 'All units' }}</b></span>
        <span>Snapshot: <b>#{{ $run->id }}</b> · @if($run->status === 'certified')<span class="badge tone-green">VERIFIED</span>@else<span class="badge tone-amber">NOT YET VERIFIED</span>@endif</span>
    </div>
    @include('reports.partials.strength-body', ['summary' => $s, 'byUnit' => $s['by_unit'] ?? [], 'rows' => $rows])
    @if($run->remarks)<div class="report-section">Remarks</div><div class="small" style="white-space:pre-line">{{ $run->remarks }}</div>@endif
    <div class="signatures">
        <div class="sig">{{ Settings::get('prepared_by_label') }}:<div class="line"></div>{{ $run->generator->name }}<div class="xs muted">{{ $run->generated_at->format('d M Y H:i') }}</div></div>
        <div class="sig">{{ Settings::get('verified_by_label') }}:<div class="line"></div>{{ $run->certifier?->name ?? '' }}<div class="xs muted">{{ $run->certified_at?->format('d M Y H:i') }}</div></div>
        <div class="sig">{{ Settings::get('noted_by_label') }}:<div class="line"></div>&nbsp;</div>
    </div>
    <div class="report-foot"><span>{{ Settings::get('report_footer_note') }}</span><span>Snapshot #{{ $run->id }} · printed {{ now()->format('d M Y H:i') }}</span></div>
</div>
@endsection
