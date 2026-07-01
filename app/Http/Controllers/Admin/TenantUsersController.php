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
use Illuminate\Validation\Rule;

class TenantUsersController extends Controller
{
    public function store(Request $request, Tenant $tenant, CreateDirectUserAction $action): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('tenant_id', $tenant->id)],
        ]);

        $role = Role::withoutGlobalScopes()->findOrFail($request->role_id);

        $action->handle($tenant, $request->name, $request->email, $role);

        return back()->with('success', __('User added successfully. Login credentials have been sent to their email.'));
    }

    public function destroy(Tenant $tenant, User $user, RemoveUserAction $action): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $action->handle($tenant, $user, request()->user());

        return back()->with('success', __('User removed from tenant.'));
    }
}
