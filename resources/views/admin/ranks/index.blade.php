@extends('layouts.app')
@section('title', 'Ranks')
@section('crumb', 'Administration')
@section('content')
<div class="page-head"><div><h2>Ranks</h2><div class="sub">Rank list and precedence (lower order = more senior). Verify against current official usage.</div></div>
    <div class="page-actions"><button class="btn btn-primary" data-dialog-open="rank-new"><x-icon name="plus" size="sm"/> Add rank</button></div></div>
<div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Order</th><th>Abbreviation</th><th>Rank</th><th>Category</th><th class="num">Personnel</th><th>Status</th><th></th></tr></thead>
    <tbody>@foreach($ranks as $r)
        <tr><td>{{ $r->sort_order }}</td><td class="strong">{{ $r->abbreviation }}</td><td>{{ $r->name }}</td><td>{{ ucfirst($r->category) }}</td><td class="num">{{ $r->personnel_count }}</td>
            <td>@if($r->is_active)<x-badge tone="green">Active</x-badge>@else<x-badge tone="gray">Inactive</x-badge>@endif</td>
            <td class="right"><button class="btn btn-secondary btn-xs" data-dialog-open="rank-{{ $r->id }}">Edit</button></td></tr>
    @endforeach</tbody>
</table></div></div>
@endsection
@push('dialogs')
@foreach($ranks->push(new \App\Models\Rank(['is_active' => true, 'category' => 'enlisted'])) as $r)
<dialog class="modal" id="rank-{{ $r->id ?? 'new' }}"><form method="POST" action="{{ $r->exists ? route('admin.ranks.update', $r) : route('admin.ranks.store') }}">@csrf @if($r->exists) @method('PUT') @endif
    <div class="modal-head"><x-icon name="award"/><h3>{{ $r->exists ? 'Edit rank' : 'Add rank' }}</h3><button class="x" type="button" data-dialog-close><x-icon name="x"/></button></div>
    <div class="modal-body"><div class="form-grid">
        <x-field label="Rank name" required class="span-2"><input name="name" class="input" value="{{ $r->name }}" required></x-field>
        <x-field label="Abbreviation" required><input name="abbreviation" class="input" value="{{ $r->abbreviation }}" required></x-field>
        <x-field label="Order" required><input type="number" name="sort_order" class="input" value="{{ $r->sort_order ?? 0 }}" required></x-field>
        <x-field label="Category" required><select name="category" class="select"><option value="officer" @selected($r->category === 'officer')>Officer</option><option value="enlisted" @selected($r->category === 'enlisted')>Enlisted</option></select></x-field>
        <label class="check" style="align-self:end"><input type="checkbox" name="is_active" value="1" @checked($r->is_active)> Active</label>
    </div></div>
    <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Cancel</button><button class="btn btn-primary">Save</button></div>
</form></dialog>
@endforeach
@endpush
