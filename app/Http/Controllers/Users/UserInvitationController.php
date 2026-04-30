<?php

namespace App\Http\Controllers\Users;

use App\Actions\Users\InviteUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\InviteUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;

class UserInvitationController extends Controller
{
    public function store(InviteUserRequest $request, InviteUserAction $action): RedirectResponse
    {
        $this->authorize('invite', User::class);

        $tenant = app('currentTenant');
        $role = Role::withoutGlobalScopes()->findOrFail($request->role_id);
        abort_unless($role->tenant_id === $tenant->id, 403);

        $action->handle($tenant, $request->email, $role, $request->user());

        return back()->with('success', __('Invitation sent successfully.'));
    }

    public function destroy(UserInvitation $invitation): RedirectResponse
    {
        $this->authorize('invite', User::class);

        $invitation->delete();

        return back()->with('success', __('Invitation cancelled.'));
    }
}
