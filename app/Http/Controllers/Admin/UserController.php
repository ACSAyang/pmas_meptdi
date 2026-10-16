<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function index(Request $request): View
    {
        return view('admin.users.index', [
            'users' => User::query()->with(['roles', 'personnel.rank'])
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%')->orWhere('username', 'like', '%'.$request->input('q').'%')->orWhere('email', 'like', '%'.$request->input('q').'%')))
                ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('slug', $request->input('role'))))
                ->when($request->input('status') === 'inactive', fn ($q) => $q->where('is_active', false))
                ->when($request->input('status') === 'active', fn ($q) => $q->where('is_active', true))
                ->orderBy('name')->paginate(25)->withQueryString(),
            'roles' => Role::query()->orderBy('name')->get(),
            'filters' => $request->only(['q', 'role', 'status']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.users.form', $this->formData(new User(['is_active' => true, 'personnel_id' => $request->integer('personnel') ?: null])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $temp = Str::password(12, symbols: false).'#1';
        $user = User::query()->create([
            'username' => $data['username'], 'name' => $data['name'], 'email' => $data['email'],
            'personnel_id' => $data['personnel_id'] ?? null, 'is_active' => true,
            'password' => Hash::make($temp), 'must_change_password' => true,
        ]);
        $user->roles()->sync($data['roles']);
        $this->audit->log('user.roles_changed', $user, null, ['roles' => Role::query()->whereIn('id', $data['roles'])->pluck('slug')->all()], 'Roles assigned at account creation');

        return redirect()->route('admin.users.edit', $user)->with('temp_password', $temp)->with('success', 'Account created. Give the temporary password to the user through a secure channel.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', $this->formData($user->load('roles')));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        if ($user->id === $request->user()->id && ! in_array(Role::query()->where('slug', Role::ADMIN)->value('id'), $data['roles'])) {
            return back()->withErrors(['roles' => 'You cannot remove your own administrator role.']);
        }
        $before = $user->roles->pluck('slug')->sort()->values()->all();
        $user->update(['username' => $data['username'], 'name' => $data['name'], 'email' => $data['email'], 'personnel_id' => $data['personnel_id'] ?? null]);
        $user->roles()->sync($data['roles']);
        $after = Role::query()->whereIn('id', $data['roles'])->pluck('slug')->sort()->values()->all();
        if ($before !== $after) {
            $this->audit->log('user.roles_changed', $user, ['roles' => $before], ['roles' => $after], 'User roles changed');
        }

        return redirect()->route('admin.users.index')->with('success', 'Account updated.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $temp = Str::password(12, symbols: false).'#1';
        $user->forceFill(['password' => Hash::make($temp), 'must_change_password' => true])->saveQuietly();
        $this->audit->log('user.password_reset_by_admin', $user, null, null, 'Temporary password issued by administrator');

        return back()->with('temp_password', $temp)->with('success', 'Temporary password issued. The user must change it at next sign-in.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot suspend your own account.');
        $data = $request->validate(['reason' => [$user->is_active ? 'required' : 'nullable', 'nullable', 'string', 'max:255']]);
        $user->update(['is_active' => ! $user->is_active, 'suspended_reason' => $user->is_active ? $data['reason'] : null]);
        $this->audit->log($user->is_active ? 'user.activated' : 'user.suspended', $user, null, null, $user->is_active ? 'Account activated' : 'Account suspended', $data['reason'] ?? null);

        return back()->with('success', $user->is_active ? 'Account activated.' : 'Account suspended. Active sessions end on the next request.');
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user?->id)],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'personnel_id' => ['nullable', 'exists:personnel,id', Rule::unique('users', 'personnel_id')->ignore($user?->id)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['exists:roles,id'],
        ], ['personnel_id.unique' => 'That personnel record is already linked to another account.']);
    }

    protected function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => Role::query()->with('permissions')->orderBy('name')->get(),
            'personnel' => Personnel::query()->where('service_status', 'active')->with(['rank', 'user'])->ordered()->get(),
        ];
    }
}
