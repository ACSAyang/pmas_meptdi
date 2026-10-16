@extends('layouts.app')
@section('title', 'User Accounts')
@section('crumb', 'Administration')
@section('content')
<div class="page-head"><div><h2>User accounts</h2><div class="sub">Accounts, linked personnel records and roles. Administrative access does not grant approval authority.</div></div>
    <div class="page-actions"><a href="{{ route('admin.users.create') }}" class="btn btn-primary"><x-icon name="user-plus" size="sm"/> New account</a></div></div>
<div class="card">
    <form method="GET" class="filters">
        <div class="field grow"><label>Search</label><input name="q" class="input" value="{{ $filters['q'] ?? '' }}" placeholder="Name, username or e-mail"></div>
        <div class="field"><label>Role</label><select name="role" class="select" data-autosubmit><option value="">All roles</option>@foreach($roles as $r)<option value="{{ $r->slug }}" @selected(($filters['role'] ?? '') === $r->slug)>{{ $r->name }}</option>@endforeach</select></div>
        <div class="field"><label>Status</label><select name="status" class="select" data-autosubmit><option value="">All</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Suspended</option></select></div>
        <div class="field"><button class="btn btn-primary">Apply</button></div>
    </form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>User</th><th>Linked personnel</th><th>Roles</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead>
        <tbody>@foreach($users as $usr)
            <tr>
                <td><div class="person"><div class="avatar sm">{{ $usr->initials() }}</div><div><div class="nm">{{ $usr->name }}</div><div class="sm">{{ $usr->username }} · {{ $usr->email }}</div></div></div></td>
                <td class="small">{{ $usr->personnel?->rank_name ?? '—' }}</td>
                <td>@foreach($usr->roles as $r)<x-badge tone="navy" class="sm">{{ $r->name }}</x-badge> @endforeach</td>
                <td class="small nowrap">{{ $usr->last_login_at?->format('d M Y H:i') ?? 'Never' }}</td>
                <td>@if($usr->is_active)<x-badge tone="green">Active</x-badge>@else<x-badge tone="red">Suspended</x-badge>@endif @if($usr->must_change_password)<x-badge tone="amber" class="sm">Temp. password</x-badge>@endif</td>
                <td class="right"><a href="{{ route('admin.users.edit', $usr) }}" class="btn btn-secondary btn-xs">Manage</a></td>
            </tr>
        @endforeach</tbody>
    </table></div>
    {{ $users->links() }}
</div>
@endsection
