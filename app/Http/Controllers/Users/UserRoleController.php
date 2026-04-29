<?php

namespace App\Http\Controllers\Users;

use App\Actions\Users\AssignRoleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignRoleRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UserRoleController extends Controller
{
    public function update(AssignRoleRequest $request, User $user, AssignRoleAction $action): RedirectResponse
    {
        $this->authorize('assignRole', User::class);

        $tenant = app('currentTenant');
        $role = Role::withoutGlobalScopes()->findOrFail($request->role_id);
        abort_unless($role->tenant_id === $tenant->id, 403);

        $action->handle($tenant, $user, $role);

        return back()->with('success', __('Role updated successfully.'));
    }
}
