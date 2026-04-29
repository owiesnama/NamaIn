<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\CreateDirectUserAction;
use App\Actions\Users\RemoveUserAction;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantUsersController extends Controller
{
    public function store(Request $request, Tenant $tenant, CreateDirectUserAction $action): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $role = Role::withoutGlobalScopes()->findOrFail($request->role_id);

        if ($role->tenant_id !== $tenant->id) {
            return back()->withErrors(['role_id' => __('This role does not belong to this tenant.')]);
        }

        $result = $action->handle($tenant, $request->name, $request->email, $role);

        return back()->with([
            'success' => __('User added successfully.'),
            'createdUser' => [
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'password' => $result['password'],
            ],
        ]);
    }

    public function destroy(Tenant $tenant, User $user, RemoveUserAction $action): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $action->handle($tenant, $user, request()->user());

        return back()->with('success', __('User removed from tenant.'));
    }
}
