@extends('layouts.app')
@section('title', 'Movement · '.$m->personnel->rank_name)
@section('crumb', 'Departures & Returns')
@section('content')
@php $u = auth()->user(); $overdue = $m->isOverdue(); $restricted = $m->application?->type?->is_sensitive && !$canSeeSensitive; @endphp
<div class="page-head">
    <div>
        <div class="row" style="gap:10px"><h2>{{ $m->personnel->rank_name }}</h2>
            @if($overdue)<x-badge tone="red" dot>Overdue</x-badge>@else<x-badge :tone="$m->status->tone()" dot>{{ $m->status->label() }}</x-badge>@endif</div>
        <div class="sub">{{ $restricted ? 'Restricted category' : $m->category->label() }} · {{ $restricted ? '' : $m->sourceLabel() }} @if($m->sourceReference())· <span class="mono">{{ $m->sourceReference() }}</span>@endif</div>
    </div>
    <div class="page-actions">
        @if($u->hasPermission('movements.record'))
            @if($m->status->value === 'scheduled')<button class="btn btn-primary" data-dialog-open="depart-dialog" data-action="{{ route('movements.depart', $m) }}" data-fill-who="{{ $m->personnel->rank_name }}"><x-icon name="depart" size="sm"/> Record departure</button>@endif
            @if($m->status->value === 'departed')<button class="btn btn-success" data-dialog-open="return-dialog" data-action="{{ route('movements.return', $m) }}" data-fill-who="{{ $m->personnel->rank_name }}"><x-icon name="arrive" size="sm"/> Record return</button>@endif
        @endif
        @if($u->hasPermission('movements.correct'))
            @if(in_array($m->status->value, ['scheduled', 'departed']))<button class="btn btn-secondary" data-dialog-open="extend-dialog"><x-icon name="clock" size="sm"/> Extend / change return</button>@endif
            @if($m->actual_departure_at)<button class="btn btn-secondary" data-dialog-open="correct-dialog"><x-icon name="edit" size="sm"/> Correct times</button>@endif
            @if($m->status->value === 'scheduled' && !$m->application_id)<button class="btn btn-outline-danger" data-dialog-open="cancel-mv-dialog"><x-icon name="ban" size="sm"/> Cancel</button>@endif
        @endif
    </div>
</div>

@if($overdue)
    <div class="alert alert-error"><x-icon name="alert"/><div><strong>Overdue since {{ $m->overdueDeadline()->format('d M Y H:i') }}</strong> ({{ $m->overdueDeadline()->diffForHumans() }}). Verify the member's status, record contact attempts in a verification case, and record the return or an authorized extension. Overdue status alone is not proof of misconduct.</div></div>
@endif

<div class="grid grid-main">
    <div class="stack">
        <div class="card">
            <div class="card-header"><h3><x-icon name="move"/> Planned vs. actual</h3></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th></th><th>Planned (authorized)</th><th>Actual (recorded)</th><th>Recorded by</th></tr></thead>
                <tbody>
                    <tr><td class="strong">Departure</td><td class="tabular">{{ $m->expected_departure_at->format('D, d M Y H:i') }}</td>
                        <td class="tabular strong">{{ $m->actual_departure_at?->format('D, d M Y H:i') ?? '—' }}</td>
                        <td class="small">{{ $m->departureRecorder?->name ?? '—' }}@if($m->departure_remarks)<div class="xs muted">{{ $m->departure_remarks }}</div>@endif</td></tr>
                    <tr><td class="strong">Return</td><td class="tabular">{{ $m->expected_return_at->format('D, d M Y H:i') }}
                            @if(!$m->expected_return_at->eq($m->original_expected_return_at))<div class="xs muted">Original: {{ $m->original_expected_return_at->format('d M Y H:i') }} (extended)</div>@endif</td>
                        <td class="tabular strong">{{ $m->actual_return_at?->format('D, d M Y H:i') ?? '—' }}@if($m->wasReturnedLate())<div class="xs" style="color:var(--orange)">After expected return</div>@endif</td>
                        <td class="small">{{ $m->returnRecorder?->name ?? '—' }}@if($m->return_remarks)<div class="xs muted">{{ $m->return_remarks }}</div>@endif</td></tr>
                </tbody>
            </table></div>
        </div>
        <div class="card">
            <div class="card-header"><h3><x-icon name="activity"/> Movement history</h3><span class="hint">from the audit trail</span></div>
            <div class="card-body">
                <ul class="timeline">
                    @forelse($history as $h)
                        <li><span class="node tone-{{ $h->actionTone() }}"></span>
                            <div class="head">{{ $h->description }}</div>
                            <div class="meta">{{ $h->user?->name ?? 'System' }} · {{ $h->created_at->format('d M Y H:i') }}</div>
                            @if($h->before_data || $h->after_data)
                                <div class="remark mono xs">@foreach(($h->after_data ?? []) as $k => $v)@if(!is_array($v)){{ $k }}: {{ $h->before_data[$k] ?? '∅' }} → {{ is_bool($v) ? ($v ? 'yes' : 'no') : $v }}
@endif @endforeach</div>
                            @endif
                            @if($h->reason)<div class="remark">Reason: {{ $h->reason }}</div>@endif
                        </li>
                    @empty
                        <li class="muted small">No history entries.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
    <div class="stack">
        <div class="card">
            <div class="card-header"><h3><x-icon name="user"/> Member</h3></div>
            <div class="card-body">
                <x-person :p="$m->personnel"/>
                <dl class="dl mt-3" style="grid-template-columns:120px 1fr">
                    <dt>Destination</dt><dd>{{ $restricted ? 'Restricted' : ($m->destination ?? '—') }}</dd>
                    <dt>Authority</dt><dd>
                        @if($m->application)<a href="{{ route('applications.show', $m->application) }}" class="mono">{{ $m->application->reference_number }}</a>
                        @elseif($m->dutyAssignment)<a href="{{ route('duty.show', $m->dutyAssignment) }}">{{ $m->dutyAssignment->title }}</a>
                        @else{{ $m->authority_reference }}@endif</dd>
                    <dt>Created</dt><dd class="small">{{ $m->created_at->format('d M Y H:i') }} by {{ $m->creator?->name }}</dd>
                </dl>
            </div>
        </div>
        @if($m->verificationCases->isNotEmpty())
        <div class="card">
            <div class="card-header"><h3><x-icon name="alert"/> Verification cases</h3></div>
            <ul class="list">@foreach($m->verificationCases as $c)
                <li><div class="grow"><div class="t">Case #{{ $c->id }} · {{ $c->reasonLabel() }}</div><div class="s">Opened {{ $c->opened_at->format('d M H:i') }}</div></div>
                    <x-badge :tone="$c->status === 'open' ? 'red' : 'green'">{{ ucfirst($c->status) }}</x-badge>
                    @can('accountability.verify')<a href="{{ route('verification.show', $c) }}" class="btn btn-secondary btn-xs">Open</a>@endcan</li>
            @endforeach</ul>
        </div>
        @endif
    </div>
</div>
@endsection

@push('dialogs')
@include('movements.partials.dialogs')
<dialog class="modal" id="extend-dialog">
    <form method="POST" action="{{ route('movements.extend', $m) }}">@csrf
        <div class="modal-head"><x-icon name="clock"/><h3>Authorized extension</h3><button class="x" type="button" data-dialog-close><x-icon name="x"/></button></div>
        <div class="modal-body stack">
            <p class="small muted mb-0">Record only an extension that has been authorized. The original expected return remains on record.</p>
            <x-field label="New expected return" required><input type="datetime-local" name="expected_return_at" class="input" value="{{ $m->expected_return_at->copy()->addDay()->format('Y-m-d\TH:i') }}" required></x-field>
            <x-field label="Authority / reason" required><textarea name="reason" class="textarea" required minlength="5" placeholder="e.g. Extension approved by the Approving Authority via phone, ref. ..."></textarea></x-field>
        </div>
        <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Cancel</button><button class="btn btn-primary">Save extension</button></div>
    </form>
</dialog>
<dialog class="modal" id="correct-dialog">
    <form method="POST" action="{{ route('movements.correct', $m) }}">@csrf
        <div class="modal-head"><x-icon name="edit"/><h3>Correct recorded times</h3><button class="x" type="button" data-dialog-close><x-icon name="x"/></button></div>
        <div class="modal-body stack">
            <p class="small muted mb-0">Corrections never erase the original values — both are kept in the audit trail with your reason (BR-014).</p>
            <div class="form-grid">
                <x-field label="Actual departure"><input type="datetime-local" name="actual_departure_at" class="input" value="{{ $m->actual_departure_at?->format('Y-m-d\TH:i') }}"></x-field>
                <x-field label="Actual return"><input type="datetime-local" name="actual_return_at" class="input" value="{{ $m->actual_return_at?->format('Y-m-d\TH:i') }}"></x-field>
            </div>
            <x-field label="Reason for correction" required><textarea name="reason" class="textarea" required minlength="5"></textarea></x-field>
        </div>
        <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Cancel</button><button class="btn btn-warning">Record correction</button></div>
    </form>
</dialog>
<dialog class="modal" id="cancel-mv-dialog">
    <form method="POST" action="{{ route('movements.cancel', $m) }}">@csrf
        <div class="modal-head"><x-icon name="ban"/><h3>Cancel scheduled movement</h3><button class="x" type="button" data-dialog-close><x-icon name="x"/></button></div>
        <div class="modal-body"><x-field label="Reason" required><textarea name="reason" class="textarea" required minlength="5"></textarea></x-field></div>
        <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Keep</button><button class="btn btn-danger">Cancel movement</button></div>
    </form>
</dialog>
@endpush
