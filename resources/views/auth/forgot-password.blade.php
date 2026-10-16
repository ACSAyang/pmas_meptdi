@extends('layouts.guest')
@section('title', 'Forgot password')
@section('content')
<div class="card"><div class="card-body">
    <h2>Reset your password</h2>
    <p class="muted mt-1">Enter the e-mail address registered to your account. If e-mail is not configured for your office, ask the System Administrator to issue a temporary password.</p>
    @include('partials.flash')
    <form method="POST" action="{{ route('password.email') }}" class="stack mt-3">
        @csrf
        <x-field label="E-mail address" name="email"><input id="email" type="email" name="email" class="input" value="{{ old('email') }}" required autofocus></x-field>
        <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
        <a href="{{ route('login') }}" class="btn btn-ghost btn-block"><x-icon name="chevron-left" size="sm"/> Back to sign in</a>
    </form>
</div></div>
@endsection
