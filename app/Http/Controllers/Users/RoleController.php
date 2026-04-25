<?php

namespace App\Http\Controllers\Users;

use App\Actions\Roles\CreateRoleAction;
use App\Actions\Roles\UpdateRoleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Role::class);

        $tenant = app('currentTenant');

        $roles = Role::withoutGlobalScopes()
            ->with('permissions')
            ->where('tenant_id', $tenant->id)
            ->withCount(['permissions', 'users'])
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'is_system' => $role->is_system,
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
                'permission_ids' => $role->permissions->pluck('id'),
            ]);

        $permissions = Permission::all()->groupBy('group')->map(fn ($perms) => $perms->map(fn (Permission $p) => [
            'id' => $p->id,
            'slug' => $p->slug,
            'description' => $p->description,
        ]));

        $permissionGroups = array_keys(PermissionSeeder::permissions());

        return inertia('Roles/Index', compact('roles', 'permissions', 'permissionGroups'));
    }

    public function store(RoleRequest $request, CreateRoleAction $action): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $action->handle(app('currentTenant'), $request->name, $request->slug, $request->permission_ids);

        return back()->with('success', __('Role created successfully.'));
    }

    public function update(RoleRequest $request, Role $role, UpdateRoleAction $action): RedirectResponse
    {
        $this->authorize('update', $role);

        $action->handle($role, $request->name, $request->permission_ids);

        return back()->with('success', __('Role updated successfully.'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => __('System roles cannot be deleted.')]);
        }

        $role->delete();

        return back()->with('success', __('Role deleted successfully.'));
    }
}
