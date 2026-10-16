@extends('layouts.app')
@section('title', $p->rank_name)
@section('crumb', 'Personnel Masterlist')
@section('content')
@php $u = auth()->user(); $current = $p->assignments->first(fn ($a) => $a->isCurrent()); @endphp

<div class="card mb-3">
    <div class="card-body">
        <div class="row between" style="align-items:flex-start;gap:18px">
            <div class="row" style="gap:16px;align-items:flex-start">
                <div class="avatar lg">{{ $p->initials }}</div>
                <div>
                    <div class="xs muted strong" style="letter-spacing:.06em;text-transform:uppercase">{{ $p->rank?->name }}</div>
                    <h2 style="font-size:22px">{{ $p->display_name }}</h2>
                    <div class="row mt-1" style="gap:8px">
                        <span class="badge tone-navy square mono">{{ $p->service_number }}</span>
                        <x-badge :tone="$p->service_status === 'active' ? 'green' : 'gray'">{{ \App\Models\Personnel::STATUSES[$p->service_status] ?? $p->service_status }}</x-badge>
                        @if($current)<span class="small muted"><x-icon name="layers" size="sm"/> {{ $current->unit->name }}</span>@endif
                        @if($p->designation)<span class="small muted">· {{ $p->designation }}</span>@endif
                    </div>
                </div>
            </div>
            <div class="page-actions">
                @if($u->hasPermission('applications.create_on_behalf') && $p->service_status === 'active')
                    <a href="{{ route('applications.create', ['personnel' => $p->id]) }}" class="btn btn-secondary"><x-icon name="plus" size="sm"/> Application on behalf</a>
                @endif
                @can('personnel.manage')
                    <button class="btn btn-secondary" data-dialog-open="reassign-dialog"><x-icon name="layers" size="sm"/> Assignment change</button>
                    <a href="{{ route('personnel.edit', $p) }}" class="btn btn-primary"><x-icon name="edit" size="sm"/> Edit</a>
                @endcan
            </div>
        </div>
    </div>
    @if($status)
    <div class="card-footer row between">
        <div class="row" style="gap:10px">
            <span class="small strong">Accountability now:</span>
            <x-category-badge :category="$status->category"/>
            <span class="small muted">{{ $status->publicDetail($canSensitiveMedical) }}</span>
        </div>
        <div class="row" style="gap:8px">
            @if($status->category->isUnresolved() && $u->hasPermission('accountability.verify'))
                <form method="POST" action="{{ route('verification.store') }}">@csrf
                    <input type="hidden" name="personnel_id" value="{{ $p->id }}">
                    <input type="hidden" name="reason" value="{{ \App\Services\VerificationService::categoryReason($status->category) }}">
                    <button class="btn btn-warning btn-sm"><x-icon name="alert" size="sm"/> Open verification case</button>
                </form>
            @endif
            @if($u->hasPermission('accountability.muster'))<a href="{{ route('muster.index', ['unit' => $current?->unit_id, 'q' => $p->service_number]) }}" class="btn btn-secondary btn-sm"><x-icon name="muster" size="sm"/> Muster entry</a>@endif
        </div>
    </div>
    @endif
</div>

<div class="card">
    <nav class="tabs">
        @foreach(['overview' => 'Overview', 'assignments' => 'Assignment history', 'applications' => 'Applications', 'movements' => 'Movements', 'accountability' => 'Accountability log', 'duty' => 'Training & duty'] as $key => $label)
            <a href="{{ route('personnel.show', [$p, 'tab' => $key]) }}" class="{{ $tab === $key ? 'active' : '' }}">{{ $label }}
                @if($key === 'applications')<span class="count">{{ $applications->total() }}</span>@endif
                @if($key === 'movements')<span class="count">{{ $movements->total() }}</span>@endif
            </a>
        @endforeach
    </nav>

    @if($tab === 'overview')
        <div class="card-body">
            <div class="grid grid-2">
                <div>
                    <h4 class="mb-2">Service information</h4>
                    <dl class="dl">
                        <dt>Service number</dt><dd class="mono">{{ $p->service_number }}</dd>
                        <dt>Rank</dt><dd>{{ $p->rank?->name }} ({{ $p->rank?->abbreviation }})</dd>
                        <dt>Full name</dt><dd>{{ $p->full_name }}</dd>
                        <dt>Sex</dt><dd>{{ ['M' => 'Male', 'F' => 'Female'][$p->sex] ?? '—' }}</dd>
                        <dt>Designation</dt><dd>{{ $p->designation ?? '—' }}</dd>
                        <dt>Current unit</dt><dd>{{ $current?->unit?->name ?? 'No active assignment' }}</dd>
                        <dt>Assigned since</dt><dd>{{ $current?->start_date?->format('d M Y') ?? '—' }}</dd>
                        <dt>System account</dt><dd>@if($p->user){{ $p->user->username }} <span class="muted small">({{ $p->user->roleNames() }})</span>@else<span class="muted">None</span>@endif</dd>
                    </dl>
                </div>
                <div>
                    <h4 class="mb-2 row" style="gap:8px">Contact &amp; emergency <x-badge tone="amber" class="sm">Restricted</x-badge></h4>
                    @if($canSensitive)
                        <dl class="dl">
                            <dt>E-mail</dt><dd>{{ $p->email ?? '—' }}</dd>
                            <dt>Contact number</dt><dd>{{ $p->contact_number ?? '—' }}</dd>
                            <dt>Emergency contact</dt><dd>{{ $p->emergency_contact_name ?? '—' }}</dd>
                            <dt>Emergency number</dt><dd>{{ $p->emergency_contact_number ?? '—' }}</dd>
                            <dt>Remarks</dt><dd>{{ $p->remarks ?? '—' }}</dd>
                        </dl>
                    @else
                        <div class="alert alert-info"><x-icon name="lock"/><div>Contact and emergency details are visible only to authorized records personnel.</div></div>
                    @endif
                </div>
            </div>
        </div>
    @elseif($tab === 'assignments')
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Unit / section</th><th>Type</th><th>Effective</th><th>Until</th><th>Order / reference</th><th>Ended because</th><th></th></tr></thead>
            <tbody>
            @foreach($p->assignments as $a)
                <tr>
                    <td class="strong">{{ $a->unit->name }}</td>
                    <td>{{ \App\Models\PersonnelAssignment::TYPES[$a->assignment_type] ?? $a->assignment_type }}</td>
                    <td class="nowrap">{{ $a->start_date->format('d M Y') }}</td>
                    <td class="nowrap">{{ $a->end_date?->format('d M Y') ?? 'Present' }}</td>
                    <td class="small mono">{{ $a->order_reference ?? '—' }}</td>
                    <td class="small">{{ $a->end_reason ?? '—' }}</td>
                    <td>@if($a->isCurrent())<x-badge tone="green" class="sm">Current</x-badge>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="card-footer small muted">Assignment periods are never overwritten so historical strength reports remain accurate.</div>
    @elseif($tab === 'applications')
        @include('applications.partials.table', ['applications' => $applications, 'showPerson' => false])
    @elseif($tab === 'movements')
        @include('movements.partials.table', ['movements' => $movements, 'showPerson' => false])
    @elseif($tab === 'accountability')
        @if($events->isEmpty())
            <x-empty icon="muster" title="No accountability entries"/>
        @else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Date &amp; time</th><th>Entry</th><th>Source</th><th>Recorded by</th><th>Remarks</th><th></th></tr></thead>
            <tbody>
            @foreach($events as $e)
                <tr style="{{ $e->voided_at ? 'opacity:.55' : '' }}">
                    <td class="nowrap tabular">{{ $e->occurred_at->format('d M Y H:i') }}</td>
                    <td>@if($e->event_type === 'sick_call' && !$canSensitiveMedical)<x-badge tone="violet">Medical (restricted)</x-badge>@else<x-badge :tone="$e->tone()">{{ $e->label() }}</x-badge>@endif</td>
                    <td class="small">{{ \App\Models\AccountabilityEvent::SOURCES[$e->source] ?? $e->source }}</td>
                    <td class="small">{{ $e->recorder?->name }}</td>
                    <td class="small">{{ $e->remarks }}@if($e->voided_at)<div class="xs" style="color:var(--red)">Voided {{ $e->voided_at->format('d M H:i') }} by {{ $e->voider?->name }}: {{ $e->void_reason }}</div>@endif</td>
                    <td>@if(!$e->voided_at && $u->hasPermission('accountability.muster'))
                        <button class="btn btn-ghost btn-xs" data-dialog-open="void-dialog" data-action="{{ route('events.void', $e) }}">Void</button>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $events->links() }}
        @endif
    @elseif($tab === 'duty')
        @if($duties->isEmpty())
            <x-empty icon="briefcase" title="No training or duty assignments"/>
        @else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Assignment</th><th>Type</th><th>Period</th><th>Reference</th><th>Status</th></tr></thead>
            <tbody>@foreach($duties as $d)
                <tr @if($u->hasPermission('duty.view')) data-href="{{ route('duty.show', $d) }}" @endif>
                    <td class="strong">{{ $d->title }}<div class="xs muted">{{ $d->location }}</div></td>
                    <td>{{ $d->assignment_type->label() }}</td>
                    <td class="small nowrap">{{ $d->start_at->format('d M Y') }} – {{ $d->end_at->format('d M Y') }}</td>
                    <td class="small mono">{{ $d->reference_number }}</td>
                    <td><x-badge :tone="$d->statusTone()">{{ \App\Models\DutyAssignment::STATUSES[$d->status] }}</x-badge></td>
                </tr>
            @endforeach</tbody>
        </table></div>
        @endif
    @endif
</div>
@endsection

@push('dialogs')
@can('personnel.manage')
<dialog class="modal" id="reassign-dialog">
    <form method="POST" action="{{ route('personnel.reassign', $p) }}">@csrf
        <div class="modal-head"><x-icon name="layers"/><h3>Assignment change</h3><button class="x" type="button" data-dialog-close><x-icon name="x"/></button></div>
        <div class="modal-body stack">
            <x-field label="Action" name="action"><select name="action" class="select" data-toggle-target="#reassign-fields" data-toggle-values="reassign">
                <option value="reassign">Reassign to another unit / section</option>
                <option value="end">End assignment (transfer out, separation, etc.)</option></select></x-field>
            <x-field label="Effective date" name="effective_date" required><input type="date" name="effective_date" class="input" value="{{ now()->toDateString() }}" required></x-field>
            <div id="reassign-fields" class="form-grid">
                <x-field label="New unit / section" name="unit_id"><select name="unit_id" class="select">@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach</select></x-field>
                <x-field label="Assignment type" name="assignment_type"><select name="assignment_type" class="select">@foreach(\App\Models\PersonnelAssignment::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></x-field>
                <x-field label="Order / reference" name="order_reference" class="span-2"><input name="order_reference" class="input"></x-field>
            </div>
            <x-field label="Reason / remarks" name="end_reason" required><input name="end_reason" class="input" required placeholder="e.g. Reassigned per office order"></x-field>
            <x-field label="Update service status (end only)" name="new_status"><select name="new_status" class="select"><option value="">Keep current</option>@foreach(\App\Models\Personnel::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></x-field>
        </div>
        <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Cancel</button><button class="btn btn-primary">Save change</button></div>
    </form>
</dialog>
@endcan
<dialog class="modal" id="void-dialog">
    <form method="POST" action="">@csrf
        <div class="modal-head"><x-icon name="ban"/><h3>Void accountability entry</h3><button class="x" type="button" data-dialog-close><x-icon name="x"/></button></div>
        <div class="modal-body"><p class="small muted">The entry is kept for audit purposes and excluded from calculations.</p>
            <x-field label="Reason" required><textarea name="reason" class="textarea" required minlength="5"></textarea></x-field></div>
        <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Cancel</button><button class="btn btn-danger">Void entry</button></div>
    </form>
</dialog>
@endpush
