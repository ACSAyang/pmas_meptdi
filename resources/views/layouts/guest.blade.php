@php use App\Support\Settings; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Sign in') · {{ Settings::get('org_short_name') }} PMAS</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230f2747'/%3E%3Cpath d='M16 6l8 3v6c0 5-8 10-8 10s-8-5-8-10V9z' fill='%23c9a227'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div class="auth">
    <section class="auth-aside">
        <div class="row" style="gap:12px">
            <div class="brand-mark">PMAS</div>
            <div class="brand-text"><strong style="color:#fff">{{ Settings::get('org_short_name') }}</strong><span style="color:#8fa5c4">{{ Settings::get('parent_org') }}</span></div>
        </div>
        <div>
            <div class="badge" style="background:rgba(201,162,39,.15);color:#f3d77a;border-color:rgba(201,162,39,.35)">Personnel Management &amp; Accountability System</div>
            <h1>Know who is present, who is away, and who still needs to be verified.</h1>
            <p>A centralized platform for the personnel masterlist, leave and pass applications, chain-of-command approvals, actual departures and returns, and daily strength reporting.</p>
            <ul class="auth-points">
                <li><x-icon name="shield"/> Authorization and actual movement are recorded separately — approval does not mean departure.</li>
                <li><x-icon name="check-circle"/> Every member falls in exactly one accountability category; totals always reconcile to assigned strength.</li>
                <li><x-icon name="activity"/> Approvals, movements and corrections are traceable in the audit trail.</li>
            </ul>
        </div>
        <div class="xs" style="color:#6d84a6">{{ Settings::get('org_name') }}</div>
    </section>
    <main class="auth-main">
        <div class="auth-card">
            @yield('content')
            <div class="restricted"><x-icon name="lock" size="sm"/> Restricted system — for authorized personnel only. All activity is monitored and logged.</div>
        </div>
    </main>
</div>
<script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
