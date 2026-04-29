<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\AssignRoleAction;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantUserRoleController extends Controller
{
    public function update(Request $request, Tenant $tenant, User $user, AssignRoleAction $action): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $role = Role::withoutGlobalScopes()->findOrFail($request->role_id);

        if ($role->tenant_id !== $tenant->id) {
            return back()->withErrors(['role_id' => __('This role does not belong to this tenant.')]);
        }

        $action->handle($tenant, $user, $role);

        return back()->with('success', __('Role updated successfully.'));
    }
}
