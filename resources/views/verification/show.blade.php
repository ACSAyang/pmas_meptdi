@extends('layouts.app')
@section('title', 'Verification Case #'.$case->id)
@section('crumb', 'Verification Cases')
@section('content')
@php $m = $case->movement; $open = $case->status === 'open'; @endphp
<div class="page-head">
    <div><div class="row" style="gap:10px"><h2>Case #{{ $case->id }} · {{ $case->personnel->rank_name }}</h2>
        @if($open)<x-badge tone="red" dot>Open</x-badge>@else<x-badge tone="green">Resolved</x-badge>@endif</div>
        <div class="sub">{{ $case->reasonLabel() }} · opened {{ $case->opened_at->format('d M Y H:i') }} by {{ $case->opener?->name ?? 'automatic check' }}</div></div>
    <div class="page-actions">
        <a href="{{ route('personnel.show', $case->personnel) }}" class="btn btn-secondary"><x-icon name="user" size="sm"/> Member record</a>
        @if($open)<button class="btn btn-primary" data-dialog-open="resolve-dialog"><x-icon name="check" size="sm"/> Resolve case</button>@endif
    </div>
</div>

<div class="grid grid-main">
    <div class="stack">
        @if($open)
        <div class="card">
            <div class="card-header"><h3><x-icon name="phone"/> Record verification action</h3></div>
            <form method="POST" action="{{ route('verification.action', $case) }}" class="card-body stack">@csrf
                <div class="form-grid">
                    <x-field label="Action" name="action_type"><select name="action_type" class="select">@foreach(\App\Models\VerificationCaseAction::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></x-field>
                </div>
                <x-field label="Details" name="details" required help="Who was contacted, how, when, and the result."><textarea name="details" class="textarea" required></textarea></x-field>
                <div class="form-actions"><button class="btn btn-primary">Add to case log</button></div>
            </form>
        </div>
        @else
        <div class="alert alert-success"><x-icon name="check-circle"/><div><strong>Resolved {{ $case->resolved_at?->format('d M Y H:i') }} by {{ $case->resolver?->name }}</strong><br>{{ $case->resolution }}</div></div>
        @endif

        <div class="card">
            <div class="card-header"><h3><x-icon name="list"/> Case log</h3></div>
            <div class="card-body">
                <ul class="timeline">
                    @forelse($case->actions as $act)
                        <li><span class="node tone-{{ $act->action_type === 'escalation' ? 'red' : ($act->action_type === 'contact_attempt' ? 'blue' : 'gray') }}"></span>
                            <div class="head">{{ $act->label() }}</div><div class="meta">{{ $act->user->name }} · {{ $act->created_at->format('d M Y H:i') }}</div>
                            <div class="remark">{{ $act->details }}</div></li>
                    @empty
                        <li class="muted small">No actions recorded yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
    <div class="stack">
        <div class="card">
            <div class="card-header"><h3><x-icon name="move"/> Related movement</h3></div>
            <div class="card-body">
                @if($m)
                    <dl class="dl" style="grid-template-columns:120px 1fr">
                        <dt>Authority</dt><dd>{{ $m->sourceLabel() }} <span class="mono small">{{ $m->sourceReference() }}</span></dd>
                        <dt>Departed</dt><dd>{{ $m->actual_departure_at?->format('d M Y H:i') ?? '—' }}</dd>
                        <dt>Expected back</dt><dd>{{ $m->expected_return_at->format('d M Y H:i') }}</dd>
                        <dt>Returned</dt><dd>{{ $m->actual_return_at?->format('d M Y H:i') ?? 'Not recorded' }}</dd>
                        <dt>Destination</dt><dd>{{ $m->application?->type?->is_sensitive && !auth()->user()->hasPermission('medical.view') ? 'Restricted' : ($m->destination ?? '—') }}</dd>
                    </dl>
                    <a href="{{ route('movements.show', $m) }}" class="btn btn-secondary btn-sm btn-block mt-2">Open movement</a>
                @else
                    <div class="muted small">No open movement is linked to this case.</div>
                @endif
            </div>
        </div>
        <div class="card"><div class="card-header"><h3><x-icon name="info"/> Guidance</h3></div>
            <div class="card-body small"><ol style="margin:0;padding-left:18px;display:grid;gap:6px">
                <li>Attempt to contact the member and record each attempt.</li>
                <li>Check whether an extension was authorized.</li>
                <li>Escalate under office policy if the member cannot be located.</li>
                <li>Resolve only with reliable information: record the actual return, the authorized extension, or confirmed presence.</li>
            </ol></div>
        </div>
    </div>
</div>
@endsection

@push('dialogs')
<dialog class="modal" id="resolve-dialog">
    <form method="POST" action="{{ route('verification.resolve', $case) }}">@csrf
        <div class="modal-head"><x-icon name="check-circle"/><h3>Resolve case #{{ $case->id }}</h3><button class="x" type="button" data-dialog-close><x-icon name="x"/></button></div>
        <div class="modal-body stack">
            <x-field label="Outcome" name="outcome" required>
                <select name="outcome" class="select" id="outcome-select">
                    @if($m && $m->status->value === 'departed')<option value="returned">Member has returned — record actual return</option><option value="extended">Authorized extension — new expected return</option>@else<option value="present">Member located and confirmed present</option>@endif
                    <option value="other">Other documented resolution (no status change)</option>
                </select>
            </x-field>
            <div class="form-grid">
                <x-field label="Actual return time (if returned)"><input type="datetime-local" name="actual_at" class="input" value="{{ now()->format('Y-m-d\TH:i') }}"></x-field>
                <x-field label="New expected return (if extended)"><input type="datetime-local" name="new_return_at" class="input" value="{{ now()->addDay()->format('Y-m-d\TH:i') }}"></x-field>
            </div>
            <x-field label="Resolution / basis" required><textarea name="resolution" class="textarea" required minlength="5" placeholder="Source of the information and who verified it"></textarea></x-field>
        </div>
        <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Cancel</button><button class="btn btn-primary">Resolve</button></div>
    </form>
</dialog>
@endpush
