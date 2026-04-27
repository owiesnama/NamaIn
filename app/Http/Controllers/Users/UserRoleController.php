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

        $role = Role::withoutGlobalScopes()->findOrFail($request->role_id);

        $action->handle(app('currentTenant'), $user, $role);

        return back()->with('success', __('Role updated successfully.'));
    }
}
