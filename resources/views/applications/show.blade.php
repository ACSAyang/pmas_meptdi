@extends('layouts.app')
@section('title', ($a->reference_number ?? 'Draft').' · '.$a->type->name)
@section('crumb', 'Applications')
@section('content')
@php
    use App\Enums\ApplicationStatus as S;
    $u = auth()->user();
    $status = $a->status;
    $decisions = $a->decisions;
    $stepDone = fn ($step) => $decisions->contains(fn ($d) => $d->approval_step_id === $step->id && in_array($d->decision, ['recommended', 'approved']) && $d->decided_at >= ($a->submitted_at ?? $a->created_at));
    $m = $a->movement;
@endphp

<div class="page-head">
    <div>
        <div class="row" style="gap:10px">
            <h2 class="mono">{{ $a->reference_number ?? 'DRAFT' }}</h2>
            <x-badge :tone="$status->tone()" dot>{{ $status->label() }}</x-badge>
            @if($a->is_late)<x-badge tone="orange">Late submission</x-badge>@endif
            @if($a->is_retrospective)<x-badge tone="orange">Retrospective</x-badge>@endif
            @if($a->type->is_sensitive)<x-badge tone="violet"><x-icon name="lock" size="sm"/> Restricted</x-badge>@endif
        </div>
        <div class="sub">{{ $a->type->name }} · {{ $a->personnel->rank_name }} · {{ $a->durationLabel() }}</div>
    </div>
    <div class="page-actions">
        <a href="{{ route('applications.print', $a) }}" class="btn btn-secondary" target="_blank"><x-icon name="printer" size="sm"/> Print summary</a>
        @can('update', $a)<a href="{{ route('applications.edit', $a) }}" class="btn btn-secondary"><x-icon name="edit" size="sm"/> Edit</a>@endcan
        @can('cancel', $a)<button class="btn btn-outline-danger" data-dialog-open="cancel-dialog"><x-icon name="ban" size="sm"/> Cancel</button>@endcan
        @can('submit', $a)
            <form method="POST" action="{{ route('applications.submit', $a) }}" data-confirm="Submit this application for review? It will be routed to {{ $workflowSteps->first()?->name ?? 'the first stage' }}." data-confirm-button="Submit">@csrf
                <button class="btn btn-primary"><x-icon name="send" size="sm"/> {{ $status === S::Returned ? 'Resubmit' : 'Submit' }}</button></form>
        @endcan
    </div>
</div>

{{-- Workflow progress --}}
<div class="card mb-3"><div class="card-body">
    <div class="steps">
        <div class="step {{ $a->submitted_at ? 'done' : 'current' }}"><span class="name">{{ $a->submitted_at ? 'Submitted' : 'Draft' }}</span><span class="xs">{{ $a->submitted_at?->format('d M H:i') }}</span></div>
        @foreach($workflowSteps as $step)
            @php
                $done = $stepDone($step);
                $isCurrent = $a->current_step_id === $step->id;
                $failed = in_array($status, [S::Rejected, S::Returned]) && $decisions->last()?->approval_step_id === $step->id;
            @endphp
            <div class="step {{ $done ? 'done' : ($failed ? 'failed' : ($isCurrent ? 'current' : '')) }}">
                <span class="name">{{ $step->name }}</span><span class="xs">{{ $step->role->name }}</span>
            </div>
        @endforeach
        @if($a->type->requires_movement)
            <div class="step {{ $m?->actual_departure_at ? 'done' : (($status === S::Approved) ? 'current' : '') }}"><span class="name">Departure recorded</span><span class="xs">{{ $m?->actual_departure_at?->format('d M H:i') }}</span></div>
            <div class="step {{ $m?->actual_return_at ? 'done' : ($m?->actual_departure_at ? 'current' : '') }}"><span class="name">Return recorded</span><span class="xs">{{ $m?->actual_return_at?->format('d M H:i') }}</span></div>
        @endif
    </div>
</div></div>

<div class="grid grid-main">
    <div class="stack">
        {{-- Decision panel --}}
        @if($canAct)
        <div class="card" style="border-color:var(--navy-500);box-shadow:0 0 0 3px rgba(42,90,152,.12)">
            <div class="card-header" style="background:var(--navy-50)"><h3><x-icon name="inbox"/> Your action — {{ $a->currentStep->name }}</h3><span class="hint">{{ \App\Models\ApprovalStep::ACTIONS[$a->currentStep->action_type] }}</span></div>
            <div class="card-body">
                @php $unverified = $a->unverifiedRequiredDocuments(); @endphp
                @if($a->currentStep->requires_documents_complete && $unverified)
                    <div class="alert alert-warning"><x-icon name="paperclip"/><div>This stage requires all required documents to be verified. Outstanding: <strong>{{ implode(', ', $unverified) }}</strong>. You may still return or reject.</div></div>
                @endif
                <form method="POST" action="{{ route('applications.decide', $a) }}" class="stack">@csrf
                    <input type="hidden" name="step_id" value="{{ $a->current_step_id }}">
                    <x-field label="Remarks" name="remarks" help="Required when returning or rejecting. Visible to the applicant."><textarea name="remarks" class="textarea" rows="3">{{ old('remarks') }}</textarea></x-field>
                    <div class="row between">
                        <div class="btn-group">
                            <button class="btn btn-secondary" name="decision" value="return"><x-icon name="undo" size="sm"/> Return for correction</button>
                            <button class="btn btn-outline-danger" name="decision" value="reject"><x-icon name="x" size="sm"/> Reject</button>
                        </div>
                        @if($a->currentStep->isFinal())
                            <button class="btn btn-success" name="decision" value="approve"><x-icon name="check" size="sm"/> Approve</button>
                        @else
                            <button class="btn btn-primary" name="decision" value="recommend"><x-icon name="send" size="sm"/> Recommend &amp; forward</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
        @elseif($actBlocker)
            <div class="alert alert-info"><x-icon name="info"/><div>{{ $actBlocker }}</div></div>
        @endif

        <div class="card">
            <div class="card-header"><h3><x-icon name="file"/> Application details</h3></div>
            <div class="card-body">
                <dl class="dl">
                    <dt>Member</dt><dd><a href="{{ route('personnel.show', $a->personnel) }}">{{ $a->personnel->rank_name }}</a> <span class="muted small mono">{{ $a->personnel->service_number }}</span></dd>
                    <dt>Unit / section</dt><dd>{{ $a->personnel->assignmentOn($a->start_at)?->unit?->name ?? '—' }}</dd>
                    <dt>Application type</dt><dd>{{ $a->type->name }} <span class="muted small">(counts as {{ $a->type->category->label() }})</span></dd>
                    <dt>Departure</dt><dd class="tabular">{{ $a->start_at->format('l, d F Y · H:i') }}</dd>
                    <dt>Expected return</dt><dd class="tabular">{{ $a->end_at->format('l, d F Y · H:i') }} <span class="muted small">({{ $a->durationLabel() }})</span></dd>
                    @if($canSeeDetails)
                        <dt>Purpose</dt><dd style="white-space:pre-line">{{ $a->purpose }}</dd>
                        <dt>Destination</dt><dd>{{ $a->destination ?? '—' }}</dd>
                        <dt>Contact while away</dt><dd>{{ $a->contact_while_away ?? '—' }}</dd>
                    @else
                        <dt>Purpose</dt><dd><x-badge tone="violet"><x-icon name="lock" size="sm"/> Restricted</x-badge></dd>
                    @endif
                    @if($a->late_reason)<dt>Late / retrospective reason</dt><dd>{{ $a->late_reason }}</dd>@endif
                    <dt>Prepared by</dt><dd>{{ $a->creator->name }} <span class="muted small">{{ $a->created_at->format('d M Y H:i') }}</span></dd>
                    @if($a->cancelled_at)<dt>Cancelled</dt><dd>{{ $a->cancelled_at->format('d M Y H:i') }} by {{ $a->canceller?->name }} — {{ $a->cancel_reason }}</dd>@endif
                </dl>
            </div>
        </div>

        {{-- Documents --}}
        <div class="card">
            <div class="card-header"><h3><x-icon name="paperclip"/> Supporting documents</h3>
                @php $missingReq = $a->missingRequiredDocuments(); @endphp
                @if($checklist)<x-badge :tone="$missingReq ? 'rose' : 'green'">{{ $missingReq ? count($missingReq).' required outstanding' : 'Required documents uploaded' }}</x-badge>@endif
            </div>
            @if(!$canSeeDetails)
                <div class="card-body"><div class="alert alert-info mb-0"><x-icon name="lock"/><div>Attachments of restricted applications are visible only to authorized personnel.</div></div></div>
            @else
            <ul class="checklist">
                @forelse($checklist as $row)
                    @php $doc = $row['document']; @endphp
                    <li>
                        <span class="state tone-{{ ['missing' => 'gray', 'uploaded' => 'amber', 'verified' => 'green', 'rejected' => 'red'][$row['state']] }}">
                            <x-icon :name="['missing' => 'file', 'uploaded' => 'clock', 'verified' => 'check', 'rejected' => 'x'][$row['state']]" size="sm"/>
                        </span>
                        <div class="grow" style="min-width:0">
                            <div class="strong">{{ $row['requirement']->name }} @if($row['requirement']->is_required)<span class="badge tone-rose sm">Required</span>@else<span class="badge tone-gray sm">Optional</span>@endif</div>
                            <div class="xs muted">
                                @if($doc)
                                    <a href="{{ route('documents.show', $doc) }}" target="_blank">{{ $doc->original_name }}</a> · {{ $doc->humanSize() }} · uploaded {{ $doc->created_at->format('d M H:i') }} by {{ $doc->uploader->name }}
                                    @if($doc->review_status !== 'pending') · {{ ucfirst($doc->review_status) }} by {{ $doc->reviewer?->name }}@endif
                                    @if($doc->review_remarks)<div style="color:var(--red)">{{ $doc->review_remarks }}</div>@endif
                                @else
                                    Not yet uploaded
                                @endif
                            </div>
                        </div>
                        <div class="btn-group">
                            @if($doc && $doc->review_status === 'pending')
                                @can('verifyDocuments', $a)
                                    <form method="POST" action="{{ route('documents.review', $doc) }}">@csrf<input type="hidden" name="review_status" value="verified"><button class="btn btn-success btn-xs"><x-icon name="check" size="sm"/> Verify</button></form>
                                    <button class="btn btn-outline-danger btn-xs" data-dialog-open="reject-doc-dialog" data-action="{{ route('documents.review', $doc) }}">Reject</button>
                                @endcan
                            @endif
                            @can('uploadDocument', $a)
                                <button class="btn btn-secondary btn-xs" data-dialog-open="upload-dialog" data-fill-requirement="{{ $row['requirement']->id }}" data-fill-req-name="{{ $row['requirement']->name }}"><x-icon name="upload" size="sm"/> {{ $doc ? 'Replace' : 'Upload' }}</button>
                            @endcan
                        </div>
                    </li>
                @empty
                    <li class="muted small">No documents are configured for this application type.</li>
                @endforelse
                @foreach($a->documents->where('is_superseded', false)->whereNull('document_requirement_id') as $doc)
                    <li><span class="state tone-navy"><x-icon name="paperclip" size="sm"/></span>
                        <div class="grow"><div class="strong">Additional attachment</div><div class="xs muted"><a href="{{ route('documents.show', $doc) }}" target="_blank">{{ $doc->original_name }}</a> · {{ $doc->humanSize() }}</div></div></li>
                @endforeach
            </ul>
            @can('uploadDocument', $a)
                <div class="card-footer"><button class="btn btn-ghost btn-sm" data-dialog-open="upload-dialog" data-fill-requirement="" data-fill-req-name="Additional attachment"><x-icon name="plus" size="sm"/> Add other attachment</button></div>
            @endcan
            @endif
        </div>
    </div>

    <div class="stack">
        @if($m)
        <div class="card">
            <div class="card-header"><h3><x-icon name="move"/> Movement</h3><x-badge :tone="$m->isOverdue() ? 'red' : $m->status->tone()">{{ $m->isOverdue() ? 'Overdue' : $m->status->shortLabel() }}</x-badge></div>
            <div class="card-body">
                <dl class="dl" style="grid-template-columns:130px 1fr">
                    <dt>Planned out</dt><dd class="tabular">{{ $m->expected_departure_at->format('d M Y H:i') }}</dd>
                    <dt>Planned return</dt><dd class="tabular">{{ $m->expected_return_at->format('d M Y H:i') }}@if(!$m->expected_return_at->eq($m->original_expected_return_at))<div class="xs muted">Originally {{ $m->original_expected_return_at->format('d M Y H:i') }}</div>@endif</dd>
                    <dt>Actual departure</dt><dd class="tabular">{!! $m->actual_departure_at ? e($m->actual_departure_at->format('d M Y H:i')).'<div class="xs muted">by '.e($m->departureRecorder?->name).'</div>' : '<span class="muted">Not recorded</span>' !!}</dd>
                    <dt>Actual return</dt><dd class="tabular">{!! $m->actual_return_at ? e($m->actual_return_at->format('d M Y H:i')).'<div class="xs muted">by '.e($m->returnRecorder?->name).'</div>' : '<span class="muted">Not recorded</span>' !!}</dd>
                </dl>
                @if($u->hasPermission('movements.view'))<a href="{{ route('movements.show', $m) }}" class="btn btn-secondary btn-sm btn-block mt-2">Open movement record</a>@endif
            </div>
        </div>
        @elseif($status === S::Approved && $a->type->requires_movement)
            <div class="alert alert-info"><x-icon name="info"/><div>Approved. A movement will be tracked by the Duty Office Watch.</div></div>
        @endif

        <div class="card">
            <div class="card-header"><h3><x-icon name="activity"/> Decision history</h3></div>
            <div class="card-body">
                <ul class="timeline">
                    @foreach($decisions->sortByDesc(fn ($d) => $d->decided_at->timestamp * 10000 + $d->id) as $d)
                        <li>
                            <span class="node tone-{{ $d->tone() }}"></span>
                            <div class="head">{{ $d->label() }}@if($d->step) <span class="muted small">· {{ $d->step->name }}</span>@endif</div>
                            <div class="meta">{{ $d->actor->name }} · {{ $d->decided_at->format('d M Y H:i') }}</div>
                            @if($d->remarks)<div class="remark">{{ $d->remarks }}</div>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@push('dialogs')
<dialog class="modal" id="upload-dialog">
    <form method="POST" action="{{ route('applications.documents.store', $a) }}" enctype="multipart/form-data">@csrf
        <div class="modal-head"><x-icon name="upload"/><h3>Upload document</h3><button class="x" type="button" data-dialog-close><x-icon name="x"/></button></div>
        <div class="modal-body stack">
            <input type="hidden" name="document_requirement_id" data-field="requirement">
            <div class="small">Requirement: <b data-field="req_name"></b></div>
            <x-field label="File" required help="PDF, JPG or PNG. Maximum {{ round(\App\Support\Settings::int('upload_max_kb', 5120) / 1024, 1) }} MB. Files are stored privately."><input type="file" name="file" class="input" accept=".pdf,.jpg,.jpeg,.png" required></x-field>
        </div>
        <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Cancel</button><button class="btn btn-primary"><x-icon name="upload" size="sm"/> Upload</button></div>
    </form>
</dialog>
<dialog class="modal" id="reject-doc-dialog">
    <form method="POST" action="">@csrf<input type="hidden" name="review_status" value="rejected">
        <div class="modal-head"><x-icon name="x-circle"/><h3>Reject document</h3><button class="x" type="button" data-dialog-close><x-icon name="x"/></button></div>
        <div class="modal-body"><x-field label="Reason (shown to the applicant)" required><textarea name="review_remarks" class="textarea" required></textarea></x-field></div>
        <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Cancel</button><button class="btn btn-danger">Reject document</button></div>
    </form>
</dialog>
<dialog class="modal" id="cancel-dialog">
    <form method="POST" action="{{ route('applications.cancel', $a) }}">@csrf
        <div class="modal-head"><x-icon name="ban"/><h3>Cancel application</h3><button class="x" type="button" data-dialog-close><x-icon name="x"/></button></div>
        <div class="modal-body"><p class="small muted">Cancellation is recorded with your name and reason. The application history is never erased (BR-013).</p>
            <x-field label="Reason for cancellation" required><textarea name="reason" class="textarea" required minlength="5"></textarea></x-field></div>
        <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Keep application</button><button class="btn btn-danger">Cancel application</button></div>
    </form>
</dialog>
@endpush
