@extends('layouts.guest')
@section('title', 'Set new password')
@section('content')
<div class="card"><div class="card-body">
    <h2>Set a new password</h2>
    @include('partials.flash')
    <form method="POST" action="{{ route('password.update') }}" class="stack mt-3">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field label="E-mail address" name="email"><input id="email" type="email" name="email" class="input" value="{{ old('email', $email) }}" required></x-field>
        <x-field label="New password" name="password" help="At least 10 characters with upper- and lower-case letters, a number and a symbol."><input id="password" type="password" name="password" class="input" autocomplete="new-password" required></x-field>
        <x-field label="Confirm new password" name="password_confirmation"><input id="password_confirmation" type="password" name="password_confirmation" class="input" autocomplete="new-password" required></x-field>
        <button class="btn btn-primary btn-block" type="submit">Reset password</button>
    </form>
</div></div>
@endsection
