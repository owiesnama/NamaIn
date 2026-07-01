<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\InviteUserAction;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantInvitationsController extends Controller
{
    public function store(Request $request, Tenant $tenant, InviteUserAction $action): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('tenant_id', $tenant->id)],
        ]);

        $role = Role::withoutGlobalScopes()->findOrFail($request->role_id);

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
