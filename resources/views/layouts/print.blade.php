@php use App\Support\Settings; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>@yield('title') · {{ Settings::get('org_short_name') }} PMAS</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    <style>body{background:#e9edf3}.print-wrap{max-width:1000px;margin:24px auto;padding:0 16px}.print-bar{display:flex;gap:8px;justify-content:flex-end;margin-bottom:12px}@media print{.print-wrap{margin:0;padding:0;max-width:none}.print-bar{display:none}body{background:#fff}}</style>
</head>
<body>
<div class="print-wrap">
    <div class="print-bar no-print">
        <button class="btn btn-secondary" onclick="history.length > 1 ? history.back() : window.close()"><x-icon name="chevron-left" size="sm"/> Back</button>
        @yield('actions')
        <button class="btn btn-primary" data-print><x-icon name="printer" size="sm"/> Print / Save as PDF</button>
    </div>
    <div class="report-sheet">
        <div class="report-header">
            <div class="org-sub">{{ Settings::get('parent_org') }}</div>
            <div class="org">{{ Settings::get('org_name') }}</div>
            <div class="title">@yield('report-title')</div>
            @hasSection('report-subtitle')<div class="small muted mt-1">@yield('report-subtitle')</div>@endif
        </div>
        @yield('content')
        <div class="report-foot">
            <span>{{ Settings::get('report_footer_note') }}</span>
            <span>Generated {{ now()->format('d M Y H:i') }} ({{ config('app.timezone') }}) by {{ auth()->user()->name }} · {{ Settings::get('org_short_name') }} PMAS</span>
        </div>
    </div>
</div>
<script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
