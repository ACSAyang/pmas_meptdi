@extends('layouts.app')
@section('title', 'Change Password')
@section('crumb', 'Account')
@section('content')
<div style="max-width:560px">
    @if(auth()->user()->must_change_password)
        <div class="notice-strip mb-3"><x-icon name="key"/> You are using a temporary password. Set your own password to continue.</div>
    @endif
    <div class="card">
        <div class="card-header"><h3><x-icon name="key"/> Change password</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('password.change.update') }}" class="stack">
                @csrf @method('PUT')
                <x-field label="Current password" name="current_password" required><input id="current_password" type="password" name="current_password" class="input" autocomplete="current-password" required></x-field>
                <x-field label="New password" name="password" required help="At least 10 characters with upper- and lower-case letters, a number and a symbol."><input id="password" type="password" name="password" class="input" autocomplete="new-password" required></x-field>
                <x-field label="Confirm new password" name="password_confirmation" required><input id="password_confirmation" type="password" name="password_confirmation" class="input" autocomplete="new-password" required></x-field>
                <div class="form-actions"><button class="btn btn-primary" type="submit">Update password</button></div>
            </form>
        </div>
        <div class="card-footer small muted">Last changed: {{ auth()->user()->password_changed_at?->format('d M Y H:i') ?? 'never' }}</div>
    </div>
</div>
@endsection
