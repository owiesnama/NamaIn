<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\AssignRoleAction;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantUserRoleController extends Controller
{
    public function update(Request $request, Tenant $tenant, User $user, AssignRoleAction $action): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $request->validate([
            'role_id' => ['required', Rule::exists('roles', 'id')->where('tenant_id', $tenant->id)],
        ]);

        $role = Role::withoutGlobalScopes()->findOrFail($request->role_id);

        $action->handle($tenant, $user, $role);

        return back()->with('success', __('Role updated successfully.'));
    }
}
