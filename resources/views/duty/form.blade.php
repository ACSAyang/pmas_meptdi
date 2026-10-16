@extends('layouts.app')
@section('title', $d->exists ? 'Edit Assignment' : 'New Assignment')
@section('crumb', 'Training & Duty')
@section('content')
<div class="page-head"><div><h2>{{ $d->exists ? 'Edit assignment' : 'New training / duty assignment' }}</h2><div class="sub">Assignments away from the unit create a scheduled movement; the Duty Office Watch records the actual departure and return.</div></div></div>
<form method="POST" action="{{ $d->exists ? route('duty.update', $d) : route('duty.store') }}" class="card" style="max-width:900px">@csrf @if($d->exists) @method('PUT') @endif
    <div class="card-body"><div class="form-grid">
        <x-field label="Member" name="personnel_id" required class="span-2">
            @if($d->exists)<input type="hidden" name="personnel_id" value="{{ $d->personnel_id }}"><input class="input" value="{{ $d->personnel->rank_name }}" readonly>
            @else<select name="personnel_id" class="select" required><option value="">Select member</option>@foreach($personnel as $p)<option value="{{ $p->id }}" @selected(old('personnel_id', $d->personnel_id) == $p->id)>{{ $p->rank?->abbreviation }} {{ $p->full_name }} — {{ $p->service_number }}</option>@endforeach</select>@endif
        </x-field>
        <x-field label="Type" name="assignment_type" required><select name="assignment_type" class="select">@foreach(\App\Enums\DutyType::cases() as $t)<option value="{{ $t->value }}" @selected(old('assignment_type', $d->assignment_type?->value) === $t->value)>{{ $t->label() }}</option>@endforeach</select></x-field>
        <x-field label="Order / reference no." name="reference_number"><input name="reference_number" class="input" value="{{ old('reference_number', $d->reference_number) }}"></x-field>
        <x-field label="Title / course / activity" name="title" required class="span-2"><input name="title" class="input" value="{{ old('title', $d->title) }}" required></x-field>
        <x-field label="Location" name="location"><input name="location" class="input" value="{{ old('location', $d->location) }}"></x-field>
        <x-field label="Responsible office / supervisor" name="responsible_office"><input name="responsible_office" class="input" value="{{ old('responsible_office', $d->responsible_office) }}"></x-field>
        <x-field label="Start" name="start_at" required><input type="datetime-local" name="start_at" class="input" value="{{ old('start_at', $d->start_at?->format('Y-m-d\TH:i')) }}" required></x-field>
        <x-field label="End" name="end_at" required><input type="datetime-local" name="end_at" class="input" value="{{ old('end_at', $d->end_at?->format('Y-m-d\TH:i')) }}" required></x-field>
        <div class="span-2">@if($d->exists)<input type="hidden" name="is_away" value="{{ $d->is_away ? 1 : 0 }}"><div class="small muted">{{ $d->is_away ? 'Away from the unit (movement tracked).' : 'In-house (member remains present).' }}</div>
            @else<label class="check"><input type="checkbox" name="is_away" value="1" @checked(old('is_away', $d->is_away))> The member will be away from the unit (track departure and return)</label>@endif</div>
        <x-field label="Remarks" name="remarks" class="span-2"><textarea name="remarks" class="textarea" rows="2">{{ old('remarks', $d->remarks) }}</textarea></x-field>
    </div></div>
    <div class="card-footer form-actions"><a href="{{ $d->exists ? route('duty.show', $d) : route('duty.index') }}" class="btn btn-secondary">Cancel</a><button class="btn btn-primary">Save assignment</button></div>
</form>
@endsection
