<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\InviteUserAction;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantInvitationsController extends Controller
{
    public function store(Request $request, Tenant $tenant, InviteUserAction $action): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $role = Role::withoutGlobalScopes()->findOrFail($request->role_id);

        if ($role->tenant_id !== $tenant->id) {
            return back()->withErrors(['role_id' => __('This role does not belong to this tenant.')]);
        }

        $action->handle($tenant, $request->email, $role, $request->user());

        return back()->with('success', __('Invitation sent successfully.'));
    }

    public function destroy(Tenant $tenant, UserInvitation $invitation): RedirectResponse
    {
        $this->authorize('update', $tenant);

        if ($invitation->tenant_id !== $tenant->id) {
            abort(404);
        }

        $invitation->delete();

        return back()->with('success', __('Invitation cancelled.'));
    }
}
