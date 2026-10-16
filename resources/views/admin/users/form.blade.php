@extends('layouts.app')
@section('title', $user->exists ? 'Manage Account' : 'New Account')
@section('crumb', 'User Accounts')
@section('content')
<div class="page-head"><div><h2>{{ $user->exists ? $user->name : 'New user account' }}</h2><div class="sub">{{ $user->exists ? 'Username '.$user->username : 'A temporary password is generated; the user must change it at first sign-in.' }}</div></div>
    @if($user->exists)
    <div class="page-actions">
        <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" data-confirm="Issue a new temporary password for {{ $user->name }}? Their current password stops working immediately." data-confirm-button="Issue password">@csrf<button class="btn btn-secondary"><x-icon name="key" size="sm"/> Issue temporary password</button></form>
        @if($user->id !== auth()->id())
            @if($user->is_active)<button class="btn btn-outline-danger" data-dialog-open="suspend-dialog"><x-icon name="ban" size="sm"/> Suspend</button>
            @else<form method="POST" action="{{ route('admin.users.toggle', $user) }}">@csrf<button class="btn btn-success"><x-icon name="check" size="sm"/> Reactivate</button></form>@endif
        @endif
    </div>
    @endif
</div>
@if(session('temp_password'))
    <div class="alert alert-warning"><x-icon name="key"/><div><strong>Temporary password:</strong> <code style="font-size:15px;user-select:all">{{ session('temp_password') }}</code><br><span class="small">Shown once. Deliver it through a secure channel. The user must change it at first sign-in.</span></div></div>
@endif
@if($user->exists && !$user->is_active)<div class="alert alert-error"><x-icon name="ban"/><div>Account suspended. {{ $user->suspended_reason }}</div></div>@endif

<form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="grid grid-main">@csrf @if($user->exists) @method('PUT') @endif
    <div class="card"><div class="card-header"><h3><x-icon name="user"/> Account</h3></div><div class="card-body"><div class="form-grid">
        <x-field label="Username" name="username" required><input name="username" class="input" value="{{ old('username', $user->username) }}" required autocomplete="off"></x-field>
        <x-field label="E-mail" name="email" required><input type="email" name="email" class="input" value="{{ old('email', $user->email) }}" required></x-field>
        <x-field label="Display name" name="name" required class="span-2"><input name="name" class="input" value="{{ old('name', $user->name) }}" required></x-field>
        <x-field label="Linked personnel record" name="personnel_id" class="span-2" help="Link the account to the member's masterlist record so they can file their own applications.">
            <select name="personnel_id" class="select"><option value="">— Not linked (e.g. system administrator) —</option>
                @foreach($personnel as $p)<option value="{{ $p->id }}" @selected(old('personnel_id', $user->personnel_id) == $p->id) @disabled($p->user && $p->user->id !== $user->id)>{{ $p->rank?->abbreviation }} {{ $p->full_name }} — {{ $p->service_number }}@if($p->user && $p->user->id !== $user->id) (has account)@endif</option>@endforeach
            </select></x-field>
    </div></div>
    <div class="card-footer form-actions"><a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Back</a><button class="btn btn-primary">{{ $user->exists ? 'Save changes' : 'Create account' }}</button></div></div>
    <div class="card"><div class="card-header"><h3><x-icon name="lock"/> Roles</h3></div>
        <div class="card-body stack">
            @error('roles')<div class="error-text">{{ $message }}</div>@enderror
            @foreach($roles as $r)
                <label class="check" style="padding:10px 12px;border:1px solid var(--border);border-radius:9px"><input type="checkbox" name="roles[]" value="{{ $r->id }}" @checked(in_array($r->id, old('roles', $user->exists ? $user->roles->pluck('id')->all() : [])))>
                    <span><span class="strong">{{ $r->name }}</span><br><span class="xs muted">{{ $r->description }}</span></span></label>
            @endforeach
        </div>
    </div>
</form>
@endsection
@push('dialogs')
@if($user->exists)
<dialog class="modal" id="suspend-dialog"><form method="POST" action="{{ route('admin.users.toggle', $user) }}">@csrf
    <div class="modal-head"><x-icon name="ban"/><h3>Suspend account</h3><button class="x" type="button" data-dialog-close><x-icon name="x"/></button></div>
    <div class="modal-body"><x-field label="Reason" required><input name="reason" class="input" required></x-field></div>
    <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Cancel</button><button class="btn btn-danger">Suspend</button></div>
</form></dialog>
@endif
@endpush
