@extends('layouts.app')
@section('title', $type->exists ? $type->name : 'New Application Type')
@section('crumb', 'Application Types')
@section('content')
<div class="page-head"><div><h2>{{ $type->exists ? $type->name : 'New application type' }}</h2><div class="sub">Changes apply to new submissions. Existing applications keep the checklist and workflow version in force when they were submitted.</div></div>
    <div class="page-actions"><a href="{{ route('admin.types.index') }}" class="btn btn-secondary">Back to list</a></div></div>

<div class="grid grid-2">
<form method="POST" action="{{ $type->exists ? route('admin.types.update', $type) : route('admin.types.store') }}" class="card">@csrf @if($type->exists) @method('PUT') @endif
    <div class="card-header"><h3><x-icon name="settings"/> Policy settings</h3></div>
    <div class="card-body"><div class="form-grid">
        <x-field label="Code" name="code" required help="Used as the reference-number prefix."><input name="code" class="input mono" value="{{ old('code', $type->code) }}" required maxlength="20"></x-field>
        <x-field label="Display order" name="sort_order"><input type="number" name="sort_order" class="input" value="{{ old('sort_order', $type->sort_order) }}"></x-field>
        <x-field label="Name" name="name" required class="span-2"><input name="name" class="input" value="{{ old('name', $type->name) }}" required></x-field>
        <x-field label="Description" name="description" class="span-2"><textarea name="description" class="textarea" rows="2">{{ old('description', $type->description) }}</textarea></x-field>
        <x-field label="Accountability category while away" name="category" required class="span-2"><select name="category" class="select">@foreach(\App\Enums\AccountabilityCategory::awayCategories() as $c)<option value="{{ $c->value }}" @selected(old('category', $type->category?->value) === $c->value)>{{ $c->label() }}</option>@endforeach</select></x-field>
        <x-field label="Notice period (days)" name="lead_time_days" help="Leave blank if no official rule. Late requests are flagged and require a reason."><input type="number" min="0" name="lead_time_days" class="input" value="{{ old('lead_time_days', $type->lead_time_days) }}"></x-field>
        <x-field label="Maximum duration (days)" name="max_duration_days" help="Leave blank if no official limit."><input type="number" min="1" name="max_duration_days" class="input" value="{{ old('max_duration_days', $type->max_duration_days) }}"></x-field>
        <div class="span-2 stack" style="--gap:8px">
            <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $type->is_active))> Enabled for new applications</label>
            <label class="check"><input type="checkbox" name="requires_destination" value="1" @checked(old('requires_destination', $type->requires_destination))> Destination / location required</label>
            <label class="check"><input type="checkbox" name="requires_movement" value="1" @checked(old('requires_movement', $type->requires_movement))> Track actual departure and return after approval</label>
            <label class="check"><input type="checkbox" name="allow_on_behalf" value="1" @checked(old('allow_on_behalf', $type->allow_on_behalf))> May be prepared on behalf of a member</label>
            <label class="check"><input type="checkbox" name="is_sensitive" value="1" @checked(old('is_sensitive', $type->is_sensitive))> Restricted (medical / sensitive) — details visible only to authorized roles</label>
        </div>
    </div></div>
    <div class="card-footer form-actions"><button class="btn btn-primary">{{ $type->exists ? 'Save policy' : 'Create type' }}</button></div>
</form>

@if($type->exists)
<div class="stack">
    <div class="card">
        <div class="card-header"><h3><x-icon name="workflow"/> Approval workflow</h3>@if($workflow)<x-badge tone="navy">Active: v{{ $workflow->version }}</x-badge>@endif</div>
        <form method="POST" action="{{ route('admin.types.workflow', $type) }}" data-confirm="Save as a new workflow version? Applications already submitted keep their current routing." data-confirm-button="Save new version">@csrf
            <div class="card-body stack" id="wf-steps">
                @error('steps')<div class="error-text">{{ $message }}</div>@enderror
                @foreach(($workflow?->steps ?? collect())->values() as $i => $s)
                    @include('admin.types.step-row', ['i' => $i, 's' => $s])
                @endforeach
            </div>
            <div class="card-footer row between">
                <button type="button" class="btn btn-ghost btn-sm" id="add-step"><x-icon name="plus" size="sm"/> Add stage</button>
                <button class="btn btn-primary btn-sm">Save as new version</button>
            </div>
        </form>
        <template id="step-template">@include('admin.types.step-row', ['i' => '__I__', 's' => null])</template>
        @if(($versions ?? collect())->count() > 1)
            <div class="card-footer xs muted">Versions: @foreach($versions as $v) v{{ $v->version }} ({{ $v->steps_count }} stages{{ $v->is_active ? ', active' : '' }})@if(!$loop->last), @endif @endforeach</div>
        @endif
    </div>

    <div class="card">
        <div class="card-header"><h3><x-icon name="paperclip"/> Document checklist</h3></div>
        <ul class="list">
            @forelse($type->documentRequirements as $r)
                <li style="{{ $r->effective_to && $r->effective_to->isPast() ? 'opacity:.5' : '' }}">
                    <div class="grow"><div class="t">{{ $r->name }}</div><div class="s">{{ $r->description }} · effective {{ $r->effective_from->format('d M Y') }}@if($r->effective_to) – {{ $r->effective_to->format('d M Y') }}@endif</div></div>
                    @if($r->is_required)<x-badge tone="rose" class="sm">Required</x-badge>@else<x-badge tone="gray" class="sm">Optional</x-badge>@endif
                    @if($r->is_sensitive)<x-badge tone="violet" class="sm">Restricted</x-badge>@endif
                    @if(!$r->effective_to || $r->effective_to->isFuture())
                        <form method="POST" action="{{ route('admin.types.requirements.retire', $r) }}" data-confirm="Retire this requirement? It will no longer apply to new applications.">@csrf<button class="btn btn-ghost btn-xs">Retire</button></form>
                    @endif
                </li>
            @empty
                <li class="muted small">No requirements configured.</li>
            @endforelse
        </ul>
        <form method="POST" action="{{ route('admin.types.requirements.store', $type) }}" class="card-footer">@csrf
            <div class="form-grid">
                <x-field label="Requirement name" class="span-2"><input name="name" class="input" required placeholder="Official form or document name"></x-field>
                <x-field label="Description"><input name="description" class="input"></x-field>
                <x-field label="Effective from"><input type="date" name="effective_from" class="input" value="{{ now()->toDateString() }}" required></x-field>
                <label class="check"><input type="checkbox" name="is_required" value="1" checked> Required</label>
                <label class="check"><input type="checkbox" name="is_sensitive" value="1"> Restricted document</label>
            </div>
            <div class="form-actions mt-2"><button class="btn btn-secondary btn-sm"><x-icon name="plus" size="sm"/> Add requirement</button></div>
        </form>
    </div>
</div>
@endif
</div>
@endsection
@push('scripts')
<script>
(function () {
    const btn = document.getElementById('add-step'); if (!btn) return;
    const box = document.getElementById('wf-steps');
    const tpl = document.getElementById('step-template').innerHTML;
    btn.addEventListener('click', () => {
        const i = box.querySelectorAll('.wf-step').length;
        box.insertAdjacentHTML('beforeend', tpl.replaceAll('__I__', i));
    });
    box.addEventListener('click', (e) => { const r = e.target.closest('[data-remove-step]'); if (r && box.querySelectorAll('.wf-step').length > 1) r.closest('.wf-step').remove(); });
})();
</script>
@endpush
