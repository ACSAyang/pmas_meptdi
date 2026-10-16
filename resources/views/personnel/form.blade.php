@extends('layouts.app')
@section('title', $p->exists ? 'Edit Personnel' : 'Add Personnel')
@section('crumb', 'Personnel Masterlist')
@section('content')
<div class="page-head">
    <div><h2>{{ $p->exists ? 'Edit '.$p->rank_name : 'Add personnel to masterlist' }}</h2>
        <div class="sub">Collect only information needed for operations. Restricted fields are visible only to authorized roles.</div></div>
</div>
<form method="POST" action="{{ $p->exists ? route('personnel.update', $p) : route('personnel.store') }}" class="stack" style="max-width:980px">
    @csrf @if($p->exists) @method('PUT') @endif
    <div class="card"><div class="card-header"><h3><x-icon name="user"/> Identification</h3></div><div class="card-body">
        <div class="form-grid cols-3">
            <x-field label="Service number" name="service_number" required><input id="service_number" name="service_number" class="input mono" value="{{ old('service_number', $p->service_number) }}" required maxlength="30"></x-field>
            <x-field label="Rank" name="rank_id" required><select id="rank_id" name="rank_id" class="select" required><option value="">Select rank</option>@foreach($ranks as $r)<option value="{{ $r->id }}" @selected(old('rank_id', $p->rank_id) == $r->id)>{{ $r->abbreviation }} – {{ $r->name }}</option>@endforeach</select></x-field>
            <x-field label="Service status" name="service_status" required><select id="service_status" name="service_status" class="select">@foreach(\App\Models\Personnel::STATUSES as $k => $v)<option value="{{ $k }}" @selected(old('service_status', $p->service_status) === $k)>{{ $v }}</option>@endforeach</select></x-field>
            <x-field label="Last name" name="last_name" required><input id="last_name" name="last_name" class="input" value="{{ old('last_name', $p->last_name) }}" required></x-field>
            <x-field label="First name" name="first_name" required><input id="first_name" name="first_name" class="input" value="{{ old('first_name', $p->first_name) }}" required></x-field>
            <x-field label="Middle name" name="middle_name"><input id="middle_name" name="middle_name" class="input" value="{{ old('middle_name', $p->middle_name) }}"></x-field>
            <x-field label="Suffix" name="suffix"><input id="suffix" name="suffix" class="input" value="{{ old('suffix', $p->suffix) }}" placeholder="Jr., III"></x-field>
            <x-field label="Sex" name="sex"><select id="sex" name="sex" class="select"><option value="">—</option><option value="M" @selected(old('sex', $p->sex) === 'M')>Male</option><option value="F" @selected(old('sex', $p->sex) === 'F')>Female</option></select></x-field>
            <x-field label="Designation / position" name="designation"><input id="designation" name="designation" class="input" value="{{ old('designation', $p->designation) }}"></x-field>
        </div>
    </div></div>

    @unless($p->exists)
    <div class="card"><div class="card-header"><h3><x-icon name="layers"/> Initial assignment</h3><span class="hint">Determines assigned strength from the start date</span></div><div class="card-body">
        <div class="form-grid cols-3">
            <x-field label="Unit / section" name="unit_id" required><select id="unit_id" name="unit_id" class="select" required><option value="">Select unit</option>@foreach($units as $u)<option value="{{ $u->id }}" @selected(old('unit_id') == $u->id)>{{ $u->name }}</option>@endforeach</select></x-field>
            <x-field label="Assignment type" name="assignment_type" required><select id="assignment_type" name="assignment_type" class="select">@foreach(\App\Models\PersonnelAssignment::TYPES as $k => $v)<option value="{{ $k }}" @selected(old('assignment_type', 'regular') === $k)>{{ $v }}</option>@endforeach</select></x-field>
            <x-field label="Effective date" name="start_date" required><input id="start_date" type="date" name="start_date" class="input" value="{{ old('start_date', now()->toDateString()) }}" required></x-field>
            <x-field label="Order / reference no." name="order_reference" class="span-2"><input id="order_reference" name="order_reference" class="input" value="{{ old('order_reference') }}"></x-field>
        </div>
    </div></div>
    @endunless

    <div class="card"><div class="card-header"><h3><x-icon name="lock"/> Contact &amp; emergency information</h3><x-badge tone="amber" class="sm">Restricted</x-badge></div><div class="card-body">
        <div class="form-grid">
            <x-field label="Official e-mail" name="email"><input id="email" type="email" name="email" class="input" value="{{ old('email', $p->email) }}"></x-field>
            <x-field label="Contact number" name="contact_number"><input id="contact_number" name="contact_number" class="input" value="{{ old('contact_number', $p->contact_number) }}"></x-field>
            <x-field label="Emergency contact name" name="emergency_contact_name"><input id="emergency_contact_name" name="emergency_contact_name" class="input" value="{{ old('emergency_contact_name', $p->emergency_contact_name) }}"></x-field>
            <x-field label="Emergency contact number" name="emergency_contact_number"><input id="emergency_contact_number" name="emergency_contact_number" class="input" value="{{ old('emergency_contact_number', $p->emergency_contact_number) }}"></x-field>
            <x-field label="Remarks" name="remarks" class="span-2"><textarea id="remarks" name="remarks" class="textarea" rows="2">{{ old('remarks', $p->remarks) }}</textarea></x-field>
        </div>
    </div></div>

    <div class="form-actions">
        <a href="{{ $p->exists ? route('personnel.show', $p) : route('personnel.index') }}" class="btn btn-secondary">Cancel</a>
        <button class="btn btn-primary" type="submit"><x-icon name="check" size="sm"/> {{ $p->exists ? 'Save changes' : 'Add to masterlist' }}</button>
    </div>
</form>
@endsection
