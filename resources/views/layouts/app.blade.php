@php
    use App\Support\Settings;
    $u = auth()->user();
    $tz = config('app.timezone');
    $can = fn ($p) => $u->hasPermission($p);
    $nav = function (string $route, string $label, string $icon, $pattern = null, $count = null, $alert = false) {
        $active = request()->routeIs($pattern ?? $route);
        return '<a href="'.route($route).'" class="'.($active ? 'active' : '').'"'.($active ? ' aria-current="page"' : '').'>'
            .\App\Support\Icon::svg($icon)
            .'<span>'.e($label).'</span>'.($count ? '<span class="count'.($alert ? ' alert' : '').'">'.$count.'</span>' : '').'</a>';
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Dashboard') · {{ Settings::get('org_short_name') }} PMAS</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230f2747'/%3E%3Cpath d='M16 6l8 3v6c0 5-8 10-8 10s-8-5-8-10V9z' fill='%23c9a227'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    @stack('head')
</head>
<body>
<div class="app">
    <aside class="sidebar" aria-label="Main navigation">
        <div class="brand">
            <div class="brand-mark">PMAS</div>
            <div class="brand-text">
                <strong>{{ Settings::get('org_short_name') }} PMAS</strong>
                <span>Personnel Management &amp; Accountability</span>
            </div>
        </div>
        <nav class="nav">
            <div class="nav-section" style="margin-top:4px">
                {!! $nav('dashboard', 'Dashboard', 'dashboard') !!}
                @if($u->personnel_id){!! $nav('profile', 'My Profile & Status', 'user') !!}@endif
            </div>

            @if($can('accountability.view') || $can('movements.view') || $can('accountability.muster'))
            <div class="nav-section">
                <div class="nav-label">Duty Office Watch</div>
                @if($can('accountability.view')){!! $nav('accountability.index', 'Accountability Board', 'shield') !!}@endif
                @if($can('accountability.muster')){!! $nav('muster.index', 'Muster / Roll Call', 'muster') !!}@endif
                @if($can('movements.view')){!! $nav('movements.index', 'Departures & Returns', 'move', 'movements.*', $navCounts['overdue'] ?? null, true) !!}@endif
                @if($can('accountability.verify')){!! $nav('verification.index', 'Verification Cases', 'alert', 'verification.*', $navCounts['cases'] ?? null, true) !!}@endif
                @if($can('movements.view') || $can('accountability.view')){!! $nav('calendar', 'Availability Calendar', 'calendar') !!}@endif
            </div>
            @endif

            <div class="nav-section">
                <div class="nav-label">Applications</div>
                @if($can('applications.create') || $can('applications.create_on_behalf')){!! $nav('applications.create', 'New Application', 'plus') !!}@endif
                {!! $nav('applications.index', $can('applications.view_all') ? 'Applications' : 'My Applications', 'file', ['applications.index', 'applications.show', 'applications.edit'], $navCounts['mine'] ?? null) !!}
                @if($can('applications.act')){!! $nav('approvals.index', 'Approval Queue', 'inbox', 'approvals.*', $navCounts['approvals'] ?? null) !!}@endif
            </div>

            @if($can('personnel.view') || $can('duty.view'))
            <div class="nav-section">
                <div class="nav-label">Personnel</div>
                @if($can('personnel.view')){!! $nav('personnel.index', 'Personnel Masterlist', 'users', ['personnel.index', 'personnel.show', 'personnel.edit', 'personnel.create', 'personnel.import*']) !!}@endif
                @if($can('duty.view')){!! $nav('duty.index', 'Training & Duty', 'briefcase', 'duty.*') !!}@endif
            </div>
            @endif

            @if($can('reports.view'))
            <div class="nav-section">
                <div class="nav-label">Reports</div>
                {!! $nav('reports.index', 'Reports', 'chart', ['reports.index', 'reports.show', 'reports.strength']) !!}
                {!! $nav('reports.snapshots', 'Saved Report Snapshots', 'archive', 'reports.snapshots*') !!}
            </div>
            @endif

            @if($can('users.manage') || $can('settings.manage') || $can('audit.view'))
            <div class="nav-section">
                <div class="nav-label">Administration</div>
                @if($can('users.manage'))
                    {!! $nav('admin.users.index', 'User Accounts', 'key', 'admin.users.*') !!}
                    {!! $nav('admin.roles.index', 'Roles & Permissions', 'lock', 'admin.roles.*') !!}
                @endif
                @if($can('settings.manage'))
                    {!! $nav('admin.types.index', 'Application Types', 'workflow', 'admin.types.*') !!}
                    {!! $nav('admin.units.index', 'Units / Sections', 'layers', 'admin.units.*') !!}
                    {!! $nav('admin.ranks.index', 'Ranks', 'award', 'admin.ranks.*') !!}
                    {!! $nav('admin.settings.edit', 'System Settings', 'settings', 'admin.settings.*') !!}
                @endif
                @if($can('audit.view')){!! $nav('admin.audit.index', 'Audit Trail', 'activity', 'admin.audit.*') !!}@endif
            </div>
            @endif
        </nav>
        <div class="sidebar-foot">
            Authorized use only. Activity is logged.<br>
            <span style="opacity:.8">{{ Settings::get('parent_org') }}</span>
        </div>
    </aside>
    <div class="overlay"></div>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-btn" type="button" data-nav-toggle aria-label="Open navigation"><x-icon name="menu"/></button>
            <div class="topbar-title">
                <div class="crumb">@yield('crumb', Settings::get('org_short_name'))</div>
                <h1>@yield('title', 'Dashboard')</h1>
            </div>
            <div class="topbar-right">
                <div class="clock" data-clock data-tz="{{ $tz }}" title="Office time ({{ $tz }})">
                    <span class="live-dot"></span><span class="lbl">Office time</span>
                    <span data-clock-text class="tabular">{{ now()->format('d M Y H:i:s') }}</span>
                </div>
                <div class="dropdown">
                    <button class="icon-btn" type="button" data-dropdown aria-label="Notifications">
                        <x-icon name="bell"/>
                        @if($unreadCount)<span class="dot">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>@endif
                    </button>
                    <div class="dropdown-menu notif-menu">
                        <div class="dropdown-head row between"><strong>Notifications</strong>
                            @if($unreadCount)
                            <form method="POST" action="{{ route('notifications.read-all') }}" data-allow-resubmit>@csrf<button class="btn btn-ghost btn-xs">Mark all read</button></form>
                            @endif
                        </div>
                        @forelse($unreadNotifications as $n)
                            <a class="notif-item" href="{{ route('notifications.open', $n->id) }}">
                                <span class="tone-dot tone-{{ $n->data['tone'] ?? 'navy' }}"></span>
                                <span><span class="t">{{ $n->data['title'] ?? 'Notification' }}</span><br>
                                <span class="b">{{ $n->data['body'] ?? '' }}</span><br>
                                <span class="when">{{ $n->created_at->diffForHumans() }}</span></span>
                            </a>
                        @empty
                            <div class="empty" style="padding:24px"><div class="small">You're all caught up.</div></div>
                        @endforelse
                        <a href="{{ route('notifications.index') }}" style="justify-content:center;border-top:1px solid var(--border);margin-top:4px;border-radius:0 0 8px 8px">View all notifications</a>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="user-chip" type="button" data-dropdown>
                        <span class="avatar">{{ $u->initials() }}</span>
                        <span class="who"><strong>{{ \Illuminate\Support\Str::limit($u->name, 26) }}</strong><span>{{ \Illuminate\Support\Str::limit($u->roleNames(), 34) }}</span></span>
                        <x-icon name="chevron-down" size="sm"/>
                    </button>
                    <div class="dropdown-menu">
                        <div class="dropdown-head"><div class="strong">{{ $u->name }}</div><div class="xs muted">{{ $u->username }} · {{ $u->email }}</div></div>
                        @if($u->personnel_id)<a href="{{ route('profile') }}"><x-icon name="user" size="sm"/> My profile &amp; status</a>@endif
                        <a href="{{ route('password.change') }}"><x-icon name="key" size="sm"/> Change password</a>
                        <a href="{{ route('notifications.index') }}"><x-icon name="bell" size="sm"/> Notifications</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="item" type="submit"><x-icon name="logout" size="sm"/> Sign out</button></form>
                    </div>
                </div>
            </div>
        </header>

        <main class="content" id="content">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>

<dialog class="modal" id="confirm-dialog" aria-labelledby="confirm-title">
    <div class="modal-head"><x-icon name="alert"/><h3 id="confirm-title">Please confirm</h3><button class="x" type="button" data-dialog-close aria-label="Close"><x-icon name="x"/></button></div>
    <div class="modal-body"><p data-confirm-text class="mb-0"></p></div>
    <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Cancel</button><button class="btn btn-primary" type="button" data-confirm-ok>Confirm</button></div>
</dialog>
@stack('dialogs')
<script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
