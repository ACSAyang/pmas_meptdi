<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>@yield('code') · PMAS</title><link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body style="display:grid;place-items:center;min-height:100vh;padding:24px">
<div class="card" style="max-width:480px;width:100%"><div class="card-body center" style="padding:36px">
    <div class="brand-mark" style="margin:0 auto 16px">PMAS</div>
    <div class="mono muted" style="font-size:13px;letter-spacing:.1em">ERROR @yield('code')</div>
    <h2 class="mt-1">@yield('heading')</h2>
    <p class="muted mt-2">@yield('message')</p>
    <div class="row mt-3" style="justify-content:center"><a href="{{ url('/') }}" class="btn btn-primary">Go to dashboard</a><a href="javascript:history.back()" class="btn btn-secondary">Go back</a></div>
</div></div>
</body></html>
