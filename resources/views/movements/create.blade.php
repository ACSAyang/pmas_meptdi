@extends('layouts.app')
@section('title', 'Direct Authorized Movement')
@section('crumb', 'Departures & Returns')
@section('content')
<div class="page-head"><div><h2>Direct authorized movement</h2><div class="sub">For authorized movements that do not require a leave/pass application (e.g. verbal authority, urgent official errand). The authority must be cited.</div></div></div>
<form method="POST" action="{{ route('movements.store') }}" class="card" style="max-width:860px">@csrf
    <div class="card-body">
        <div class="form-grid">
            <x-field label="Member" name="personnel_id" required class="span-2"><select name="personnel_id" class="select" required><option value="">Select member</option>@foreach($personnel as $p)<option value="{{ $p->id }}" @selected(old('personnel_id', $selected) == $p->id)>{{ $p->rank?->abbreviation }} {{ $p->full_name }} — {{ $p->service_number }}</option>@endforeach</select></x-field>
            <x-field label="Category" name="category" required><select name="category" class="select">@foreach(\App\Enums\AccountabilityCategory::awayCategories() as $c)<option value="{{ $c->value }}" @selected(old('category', 'official') === $c->value)>{{ $c->label() }}</option>@endforeach</select></x-field>
            <x-field label="Authority / reference" name="authority_reference" required help="Who authorized it and how (order no., memo, verbal authority of …)."><input name="authority_reference" class="input" value="{{ old('authority_reference') }}" required maxlength="150"></x-field>
            <x-field label="Expected departure" name="expected_departure_at" required><input type="datetime-local" name="expected_departure_at" class="input" value="{{ old('expected_departure_at', now()->format('Y-m-d\TH:i')) }}" required></x-field>
            <x-field label="Expected return" name="expected_return_at" required><input type="datetime-local" name="expected_return_at" class="input" value="{{ old('expected_return_at', now()->addHours(4)->format('Y-m-d\TH:i')) }}" required></x-field>
            <x-field label="Destination" name="destination" class="span-2"><input name="destination" class="input" value="{{ old('destination') }}" maxlength="200"></x-field>
            <x-field label="Remarks" name="remarks" class="span-2"><input name="remarks" class="input" value="{{ old('remarks') }}" maxlength="255"></x-field>
            <div class="span-2"><label class="check"><input type="checkbox" name="depart_now" value="1" @checked(old('depart_now', true))> The member is departing now — record the actual departure immediately</label></div>
        </div>
    </div>
    <div class="card-footer form-actions"><a href="{{ route('movements.index') }}" class="btn btn-secondary">Cancel</a><button class="btn btn-primary"><x-icon name="check" size="sm"/> Record movement</button></div>
</form>
@endsection
