<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->with('permissions')->withCount('users')->orderBy('id')->get(),
            'permissions' => Permission::query()->orderBy('id')->get()->groupBy('group'),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $matrix = $request->validate(['matrix' => ['array'], 'matrix.*' => ['array'], 'matrix.*.*' => ['integer', 'exists:permissions,id']])['matrix'] ?? [];
        $adminRole = Role::query()->where('slug', Role::ADMIN)->first();
        $usersManage = Permission::query()->where('slug', 'users.manage')->value('id');

        foreach (Role::query()->with('permissions')->get() as $role) {
            $ids = array_map('intval', $matrix[$role->id] ?? []);
            if ($role->id === $adminRole?->id && ! in_array($usersManage, $ids, true)) {
                $ids[] = $usersManage; // never lock everyone out of account management
            }
            $before = $role->permissions->pluck('slug')->sort()->values()->all();
            $role->permissions()->sync($ids);
            $after = Permission::query()->whereIn('id', $ids)->pluck('slug')->sort()->values()->all();
            if ($before !== $after) {
                $audit->log('role.permissions_changed', $role, ['permissions' => array_values(array_diff($before, $after))], ['permissions' => array_values(array_diff($after, $before))], 'Permissions changed for role '.$role->name.' (before = removed, after = added)');
            }
        }

        return back()->with('success', 'Role permissions saved.');
    }
}
