@extends('layouts.app')
@section('title', 'Roles & Permissions')
@section('crumb', 'Administration')
@section('content')
<div class="page-head"><div><h2>Roles &amp; permissions</h2><div class="sub">Permission matrix enforced on the server for every request. Approval stages are additionally bound to roles in each workflow.</div></div></div>
<form method="POST" action="{{ route('admin.roles.update') }}" class="card">@csrf @method('PUT')
    <div class="table-wrap"><table class="table compact">
        <thead><tr><th style="min-width:280px">Permission</th>@foreach($roles as $r)<th class="center" style="white-space:normal;min-width:90px">{{ $r->name }}<div class="xs muted" style="text-transform:none;letter-spacing:0;font-weight:500">{{ $r->users_count }} user(s)</div></th>@endforeach</tr></thead>
        <tbody>
        @foreach($permissions as $group => $perms)
            <tr><td colspan="{{ $roles->count() + 1 }}" style="background:var(--navy-50);font-weight:700;color:var(--navy-700)">{{ $group }}</td></tr>
            @foreach($perms as $perm)
                <tr><td><div class="strong small">{{ $perm->name }}</div><div class="xs muted mono">{{ $perm->slug }}</div></td>
                    @foreach($roles as $r)<td class="center"><input type="checkbox" class="checkbox" name="matrix[{{ $r->id }}][]" value="{{ $perm->id }}" @checked($r->permissions->contains('id', $perm->id)) aria-label="{{ $r->name }}: {{ $perm->name }}"></td>@endforeach
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table></div>
    <div class="card-footer row between"><span class="small muted">Changes are recorded in the audit trail. Grant medical/sensitive access only where there is an operational need.</span>
        <button class="btn btn-primary" data-confirm-button="Save"><x-icon name="check" size="sm"/> Save permissions</button></div>
</form>
@endsection
