@extends('layouts.print')
@section('title', $a->reference_number ?? 'Application')
@section('report-title', $a->type->name.' — Application Summary')
@section('report-subtitle', 'System summary. Use the official MEPTDI form where one is required.')
@section('content')
<div class="report-meta">
    <span>Reference: <b class="mono">{{ $a->reference_number ?? 'DRAFT' }}</b></span>
    <span>Status: <b>{{ $a->status->label() }}</b></span>
    <span>Submitted: <b>{{ $a->submitted_at?->format('d M Y H:i') ?? '—' }}</b></span>
</div>
<table class="report-table">
    <tr><th style="width:28%;text-align:left">Service number</th><td>{{ $a->personnel->service_number }}</td></tr>
    <tr><th style="text-align:left">Rank and name</th><td>{{ $a->personnel->rank_name }}</td></tr>
    <tr><th style="text-align:left">Unit / section</th><td>{{ $a->personnel->assignmentOn($a->start_at)?->unit?->name }}</td></tr>
    <tr><th style="text-align:left">Type</th><td>{{ $a->type->name }}</td></tr>
    <tr><th style="text-align:left">Inclusive period</th><td>{{ $a->start_at->format('d M Y H:i') }} to {{ $a->end_at->format('d M Y H:i') }} ({{ $a->durationLabel() }})</td></tr>
    @if($canSeeDetails)
    <tr><th style="text-align:left">Purpose</th><td>{{ $a->purpose }}</td></tr>
    <tr><th style="text-align:left">Destination</th><td>{{ $a->destination ?? '—' }}</td></tr>
    <tr><th style="text-align:left">Contact while away</th><td>{{ $a->contact_while_away ?? '—' }}</td></tr>
    @else
    <tr><th style="text-align:left">Purpose</th><td>Restricted</td></tr>
    @endif
    @if($a->late_reason)<tr><th style="text-align:left">Late / retrospective reason</th><td>{{ $a->late_reason }}</td></tr>@endif
</table>
<div class="report-section">Decision history</div>
<table class="report-table">
    <thead><tr><th>Date &amp; time</th><th>Action</th><th>Stage</th><th>By</th><th>Remarks</th></tr></thead>
    <tbody>@foreach($a->decisions as $d)<tr><td class="num">{{ $d->decided_at->format('d M Y H:i') }}</td><td>{{ $d->label() }}</td><td>{{ $d->step?->name ?? '—' }}</td><td>{{ $d->actor->name }}</td><td>{{ $d->remarks }}</td></tr>@endforeach</tbody>
</table>
@if($a->movement)
<div class="report-section">Movement record</div>
<table class="report-table">
    <thead><tr><th>Planned departure</th><th>Actual departure</th><th>Planned return</th><th>Actual return</th></tr></thead>
    <tbody><tr>
        <td class="num">{{ $a->movement->expected_departure_at->format('d M Y H:i') }}</td>
        <td class="num">{{ $a->movement->actual_departure_at?->format('d M Y H:i') ?? 'Not recorded' }}</td>
        <td class="num">{{ $a->movement->expected_return_at->format('d M Y H:i') }}</td>
        <td class="num">{{ $a->movement->actual_return_at?->format('d M Y H:i') ?? 'Not recorded' }}</td>
    </tr></tbody>
</table>
@endif
<div class="signatures">
    <div class="sig">Applicant<div class="line"></div>{{ $a->personnel->rank_name }}</div>
    <div class="sig">Recommending<div class="line"></div>&nbsp;</div>
    <div class="sig">Approved<div class="line"></div>&nbsp;</div>
</div>
@endsection
