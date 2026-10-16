@extends('layouts.guest')
@section('title', 'Sign in')
@section('content')
<div class="card">
    <div class="card-body">
        <h2>Sign in</h2>
        <p class="muted mt-1">Use your PMAS username or e-mail address.</p>
        @include('partials.flash')
        <form method="POST" action="{{ route('login.attempt') }}" class="stack mt-3" novalidate>
            @csrf
            <x-field label="Username or e-mail" name="login">
                <input id="login" name="login" class="input @error('login') is-invalid @enderror" value="{{ old('login') }}" autocomplete="username" autofocus required>
            </x-field>
            <x-field label="Password" name="password">
                <input id="password" name="password" type="password" class="input" autocomplete="current-password" required>
            </x-field>
            <div class="row between">
                <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
                <a class="small" href="{{ route('password.request') }}">Forgot password?</a>
            </div>
            <button class="btn btn-primary btn-lg btn-block" type="submit"><x-icon name="lock" size="sm"/> Sign in</button>
        </form>
        @if(app()->environment('local'))
            <div class="alert alert-info mt-3 mb-0 small"><x-icon name="info" size="sm"/>
                <div><strong>Demo accounts</strong> (local only) — password <code>Pmas@2026</code><br>
                <code>admin</code> · <code>dow</code> · <code>records</code> · <code>reviewer</code> · <code>approver</code> · <code>command</code> · <code>member</code></div>
            </div>
        @endif
    </div>
</div>
@endsection
