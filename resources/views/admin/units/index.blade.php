@extends('layouts.app')
@section('title', 'Units / Sections')
@section('crumb', 'Administration')
@section('content')
<div class="page-head"><div><h2>Units / sections</h2><div class="sub">Organizational structure used for assignments, filters and strength reports. Replace the sample units with the official MEPTDI structure.</div></div>
    <div class="page-actions"><button class="btn btn-primary" data-dialog-open="unit-new"><x-icon name="plus" size="sm"/> Add unit</button></div></div>
<div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Code</th><th>Name</th><th>Parent</th><th class="num">Current members</th><th>Order</th><th>Status</th><th></th></tr></thead>
    <tbody>@foreach($units as $u)
        <tr><td class="mono strong">{{ $u->code }}</td><td>{{ $u->name }}</td><td class="small">{{ $u->parent?->name ?? '—' }}</td><td class="num">{{ $u->current_count }}</td><td>{{ $u->sort_order }}</td>
            <td>@if($u->is_active)<x-badge tone="green">Active</x-badge>@else<x-badge tone="gray">Inactive</x-badge>@endif</td>
            <td class="right"><button class="btn btn-secondary btn-xs" data-dialog-open="unit-{{ $u->id }}">Edit</button></td></tr>
    @endforeach</tbody>
</table></div></div>
@endsection
@push('dialogs')
@foreach($units->push(new \App\Models\Unit(['is_active' => true])) as $u)
<dialog class="modal" id="unit-{{ $u->id ?? 'new' }}"><form method="POST" action="{{ $u->exists ? route('admin.units.update', $u) : route('admin.units.store') }}">@csrf @if($u->exists) @method('PUT') @endif
    <div class="modal-head"><x-icon name="layers"/><h3>{{ $u->exists ? 'Edit unit' : 'Add unit' }}</h3><button class="x" type="button" data-dialog-close><x-icon name="x"/></button></div>
    <div class="modal-body"><div class="form-grid">
        <x-field label="Code" required><input name="code" class="input" value="{{ $u->code }}" required></x-field>
        <x-field label="Display order"><input type="number" name="sort_order" class="input" value="{{ $u->sort_order ?? 0 }}"></x-field>
        <x-field label="Name" required class="span-2"><input name="name" class="input" value="{{ $u->name }}" required></x-field>
        <x-field label="Parent unit" class="span-2"><select name="parent_id" class="select"><option value="">— None (top level) —</option>@foreach($units as $p)@if($p->exists && $p->id !== $u->id)<option value="{{ $p->id }}" @selected($u->parent_id === $p->id)>{{ $p->name }}</option>@endif @endforeach</select></x-field>
        <label class="check span-2"><input type="checkbox" name="is_active" value="1" @checked($u->is_active)> Active</label>
    </div></div>
    <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Cancel</button><button class="btn btn-primary">Save</button></div>
</form></dialog>
@endforeach
@endpush
