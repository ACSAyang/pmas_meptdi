@extends('layouts.app')
@section('title', $d->title)
@section('crumb', 'Training & Duty')
@section('content')
<div class="page-head">
    <div><div class="row" style="gap:10px"><h2>{{ $d->title }}</h2><x-badge :tone="$d->statusTone()">{{ \App\Models\DutyAssignment::STATUSES[$d->status] }}</x-badge></div>
        <div class="sub">{{ $d->assignment_type->label() }} · {{ $d->personnel->rank_name }}</div></div>
    <div class="page-actions">
        @can('duty.manage')
            @if(!in_array($d->status, ['completed', 'cancelled']))
                <a href="{{ route('duty.edit', $d) }}" class="btn btn-secondary"><x-icon name="edit" size="sm"/> Edit</a>
                @if(!$d->movement?->actual_departure_at)<button class="btn btn-outline-danger" data-dialog-open="cancel-duty"><x-icon name="ban" size="sm"/> Cancel</button>@endif
            @endif
        @endcan
    </div>
</div>
<div class="grid grid-main">
    <div class="card"><div class="card-header"><h3><x-icon name="briefcase"/> Details</h3></div><div class="card-body">
        <dl class="dl">
            <dt>Member</dt><dd><a href="{{ route('personnel.show', $d->personnel) }}">{{ $d->personnel->rank_name }}</a></dd>
            <dt>Type</dt><dd>{{ $d->assignment_type->label() }} <span class="muted small">(counts as {{ $d->assignment_type->category()->label() }} when away)</span></dd>
            <dt>Period</dt><dd>{{ $d->start_at->format('d M Y H:i') }} – {{ $d->end_at->format('d M Y H:i') }}</dd>
            <dt>Location</dt><dd>{{ $d->location ?? '—' }}</dd>
            <dt>Reference</dt><dd class="mono">{{ $d->reference_number ?? '—' }}</dd>
            <dt>Responsible office</dt><dd>{{ $d->responsible_office ?? '—' }}</dd>
            <dt>Away from unit</dt><dd>{{ $d->is_away ? 'Yes — movement tracked' : 'No — in-house' }}</dd>
            <dt>Remarks</dt><dd>{{ $d->remarks ?? '—' }}</dd>
            <dt>Recorded by</dt><dd class="small">{{ $d->creator?->name }} · {{ $d->created_at->format('d M Y H:i') }}</dd>
        </dl>
    </div></div>
    <div class="card"><div class="card-header"><h3><x-icon name="move"/> Movement</h3></div><div class="card-body">
        @if($d->movement)
            <x-badge :tone="$d->movement->isOverdue() ? 'red' : $d->movement->status->tone()">{{ $d->movement->isOverdue() ? 'Overdue' : $d->movement->status->label() }}</x-badge>
            <dl class="dl mt-2" style="grid-template-columns:110px 1fr">
                <dt>Departed</dt><dd>{{ $d->movement->actual_departure_at?->format('d M Y H:i') ?? 'Not recorded' }}</dd>
                <dt>Returned</dt><dd>{{ $d->movement->actual_return_at?->format('d M Y H:i') ?? 'Not recorded' }}</dd>
            </dl>
            @can('movements.view')<a href="{{ route('movements.show', $d->movement) }}" class="btn btn-secondary btn-sm btn-block mt-2">Open movement</a>@endcan
        @else
            <div class="muted small">No movement — in-house assignment.</div>
        @endif
    </div></div>
</div>
@endsection
@push('dialogs')
<dialog class="modal" id="cancel-duty"><form method="POST" action="{{ route('duty.cancel', $d) }}">@csrf
    <div class="modal-head"><x-icon name="ban"/><h3>Cancel assignment</h3><button class="x" type="button" data-dialog-close><x-icon name="x"/></button></div>
    <div class="modal-body"><x-field label="Reason" required><textarea name="reason" class="textarea" required minlength="5"></textarea></x-field></div>
    <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Keep</button><button class="btn btn-danger">Cancel assignment</button></div>
</form></dialog>
@endpush
