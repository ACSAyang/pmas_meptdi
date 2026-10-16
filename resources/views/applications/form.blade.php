@extends('layouts.app')
@section('title', $a->exists ? 'Edit Application' : 'New Application')
@section('crumb', 'Applications')
@section('content')
@php
    $typeData = $types->mapWithKeys(fn ($t) => [$t->id => [
        'name' => $t->name,
        'description' => $t->description,
        'lead' => $t->lead_time_days,
        'max' => $t->max_duration_days,
        'destination' => $t->requires_destination,
        'sensitive' => $t->is_sensitive,
        'category' => $t->category->label(),
        'docs' => $t->documentRequirements->map(fn ($r) => ['name' => $r->name, 'required' => $r->is_required])->values(),
    ]]);
    $selectedType = old('application_type_id', $a->application_type_id ?? $types->first()?->id);
@endphp
<div class="page-head">
    <div><h2>{{ $a->exists ? 'Edit application '.($a->reference_number ?? '(draft)') : 'New application' }}</h2>
        <div class="sub">Submission and recommendation are not approval. Approval is not departure — report to the Duty Office Watch when you leave and return.</div></div>
</div>

@if($a->exists && $a->status->value === 'returned')
    @php $ret = $a->decisions()->where('decision', 'returned')->latest('decided_at')->first(); @endphp
    @if($ret)<div class="alert alert-warning"><x-icon name="undo"/><div><strong>Returned for correction by {{ $ret->actor->name }}</strong> ({{ $ret->decided_at->format('d M Y H:i') }})<br>{{ $ret->remarks }}</div></div>@endif
@endif

<form method="POST" action="{{ $a->exists ? route('applications.update', $a) : route('applications.store') }}" class="grid grid-main">
    @csrf @if($a->exists) @method('PUT') @endif
    <div class="stack">
        <div class="card">
            <div class="card-header"><h3><x-icon name="user"/> Applicant</h3></div>
            <div class="card-body">
                @if($a->exists)
                    <x-person :p="$a->personnel" :link="false"/>
                    <input type="hidden" name="personnel_id" value="{{ $a->personnel_id }}">
                @elseif($onBehalf)
                    <x-field label="Member" name="personnel_id" required help="You may prepare an application on behalf of a member. The action is recorded in the audit trail.">
                        <select id="personnel_id" name="personnel_id" class="select" required>
                            @foreach($personnelOptions as $p)<option value="{{ $p->id }}" @selected(old('personnel_id', $a->personnel_id) == $p->id)>{{ $p->rank?->abbreviation }} {{ $p->full_name }} — {{ $p->service_number }}@if($p->id === auth()->user()->personnel_id) (me)@endif</option>@endforeach
                        </select>
                    </x-field>
                @elseif($self)
                    <x-person :p="$self" :link="false"/>
                    <input type="hidden" name="personnel_id" value="{{ $self->id }}">
                @else
                    <div class="alert alert-error mb-0"><x-icon name="alert"/><div>Your account is not linked to a personnel record. Contact the System Administrator.</div></div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3><x-icon name="file"/> Application details</h3></div>
            <div class="card-body">
                <div class="form-grid">
                    <x-field label="Application type" name="application_type_id" required class="span-2">
                        <select id="application_type_id" name="application_type_id" class="select" required data-type-select>
                            @foreach($types as $t)<option value="{{ $t->id }}" @selected($selectedType == $t->id)>{{ $t->name }}</option>@endforeach
                        </select>
                    </x-field>
                    <x-field label="Departure (date &amp; time)" name="start_at" required>
                        <input id="start_at" type="datetime-local" name="start_at" class="input" value="{{ old('start_at', $a->start_at?->format('Y-m-d\TH:i')) }}" required data-start>
                    </x-field>
                    <x-field label="Expected return (date &amp; time)" name="end_at" required>
                        <input id="end_at" type="datetime-local" name="end_at" class="input" value="{{ old('end_at', $a->end_at?->format('Y-m-d\TH:i')) }}" required data-end>
                    </x-field>
                    <div class="span-2"><div class="notice-strip" data-duration style="display:none"><x-icon name="clock" size="sm"/><span data-duration-text></span></div></div>
                    <x-field label="Purpose / reason" name="purpose" required class="span-2">
                        <textarea id="purpose" name="purpose" class="textarea" rows="3" required maxlength="2000">{{ old('purpose', $a->purpose) }}</textarea>
                    </x-field>
                    <x-field label="Destination / location" name="destination" help="Required only where policy requires it." class="span-2" >
                        <input id="destination" name="destination" class="input" value="{{ old('destination', $a->destination) }}" maxlength="200" data-destination>
                    </x-field>
                    <x-field label="Contact number while away" name="contact_while_away">
                        <input id="contact_while_away" name="contact_while_away" class="input" value="{{ old('contact_while_away', $a->contact_while_away) }}" maxlength="100">
                    </x-field>
                    <div></div>
                    <x-field label="Reason for late or retrospective request" name="late_reason" class="span-2" help="Required when the request is inside the configured notice period or the departure date has already passed.">
                        <textarea id="late_reason" name="late_reason" class="textarea" rows="2" maxlength="1000">{{ old('late_reason', $a->late_reason) }}</textarea>
                    </x-field>
                </div>
            </div>
            <div class="card-footer row between">
                <a href="{{ $a->exists ? route('applications.show', $a) : route('applications.index') }}" class="btn btn-ghost">Cancel</a>
                <div class="btn-group">
                    <button class="btn btn-secondary" name="intent" value="draft" type="submit"><x-icon name="archive" size="sm"/> Save as draft</button>
                    <button class="btn btn-primary" name="intent" value="submit" type="submit"><x-icon name="send" size="sm"/> Save &amp; submit</button>
                </div>
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="card" style="position:sticky;top:84px">
            <div class="card-header"><h3><x-icon name="info"/> Policy &amp; requirements</h3></div>
            <div class="card-body">
                <div class="strong" data-p-name></div>
                <div class="small muted mt-1" data-p-desc></div>
                <dl class="dl mt-2" style="grid-template-columns:140px 1fr">
                    <dt>Counts as</dt><dd data-p-cat></dd>
                    <dt>Notice period</dt><dd data-p-lead></dd>
                    <dt>Maximum duration</dt><dd data-p-max></dd>
                    <dt>Destination</dt><dd data-p-dest></dd>
                </dl>
                <div class="alert alert-warning mt-2 mb-0" data-p-sensitive style="display:none"><x-icon name="lock" size="sm"/><div class="small">Restricted category. Details and attachments are visible only to you, the assigned approvers and authorized records staff.</div></div>
                <div class="label mt-3">Supporting documents</div>
                <ul class="list" data-p-docs style="border:1px solid var(--border);border-radius:8px"></ul>
                <div class="xs muted mt-2">Upload documents after saving. Required documents must be verified before final approval.</div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const types = @json($typeData);
    const sel = document.querySelector('[data-type-select]');
    const q = (s) => document.querySelector(s);
    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    function render() {
        const t = types[sel.value]; if (!t) return;
        q('[data-p-name]').textContent = t.name;
        q('[data-p-desc]').textContent = t.description || '';
        q('[data-p-cat]').textContent = t.category;
        q('[data-p-lead]').textContent = t.lead ? t.lead + ' day(s) before departure' : 'Not configured';
        q('[data-p-max]').textContent = t.max ? t.max + ' day(s)' : 'Not configured';
        q('[data-p-dest]').textContent = t.destination ? 'Required' : 'Optional';
        q('[data-p-sensitive]').style.display = t.sensitive ? '' : 'none';
        const dest = q('[data-destination]'); if (dest) dest.required = !!t.destination;
        q('[data-p-docs]').innerHTML = t.docs.length ? t.docs.map((d) => '<li style="padding:9px 12px"><span class="grow small">' + esc(d.name) + '</span>' + (d.required ? '<span class="badge tone-rose sm">Required</span>' : '<span class="badge tone-gray sm">Optional</span>') + '</li>').join('') : '<li class="small muted" style="padding:9px 12px">No documents configured.</li>';
        duration();
    }
    function duration() {
        const s = new Date(q('[data-start]').value), e = new Date(q('[data-end]').value);
        const box = q('[data-duration]');
        if (isNaN(s) || isNaN(e)) { box.style.display = 'none'; return; }
        box.style.display = '';
        const mins = (e - s) / 60000;
        if (mins <= 0) { q('[data-duration-text]').textContent = 'The return must be after the departure.'; box.style.background = 'var(--red-bg)'; return; }
        box.style.background = '';
        const d = Math.floor(mins / 1440), h = Math.floor((mins % 1440) / 60);
        const t = types[sel.value];
        let txt = 'Duration: ' + (d ? d + ' day(s) ' : '') + (h ? h + ' hr(s)' : (d ? '' : Math.round(mins) + ' min'));
        if (t && t.max && mins > t.max * 1440) txt += ' — exceeds the configured maximum of ' + t.max + ' day(s).';
        const daysAhead = Math.floor((s - new Date().setHours(0,0,0,0)) / 86400000);
        if (s < new Date(new Date().setHours(0,0,0,0))) txt += ' · Retrospective request — give a reason below.';
        else if (t && t.lead && daysAhead < t.lead) txt += ' · Inside the ' + t.lead + '-day notice period — give a reason below.';
        q('[data-duration-text]').textContent = txt;
    }
    sel.addEventListener('change', render);
    q('[data-start]').addEventListener('change', duration);
    q('[data-end]').addEventListener('change', duration);
    render();
})();
</script>
@endpush
