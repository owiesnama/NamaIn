<?php

namespace App\Http\Controllers\Users;

use App\Actions\Users\AssignRoleAction;
use App\Actions\Users\CreateDirectUserAction;
use App\Actions\Users\RemoveUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignRoleRequest;
use App\Http\Requests\CreateDirectUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        $tenant = app('currentTenant');

        $members = $tenant->users()
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_photo_url' => $user->profile_photo_url,
                'role' => $user->pivot->role,
                'role_id' => $user->pivot->role_id,
                'is_active' => (bool) $user->pivot->is_active,
                'joined_at' => $user->pivot->created_at,
            ]);

        $invitations = UserInvitation::with('inviter', 'role')
            ->pending()
            ->get()
            ->map(fn (UserInvitation $inv) => [
                'id' => $inv->id,
                'email' => $inv->email,
                'role' => ['id' => $inv->role->id, 'name' => $inv->role->name],
                'invited_by' => $inv->inviter->name,
                'expires_at' => $inv->expires_at,
            ]);

        $roles = Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', '!=', 'owner')
            ->get(['id', 'name', 'slug']);

        return inertia('Users/Index', compact('members', 'invitations', 'roles'));
    }

    public function store(CreateDirectUserRequest $request, CreateDirectUserAction $action): RedirectResponse
    {
        $this->authorize('invite', User::class);

        $role = Role::withoutGlobalScopes()->findOrFail($request->role_id);

        $result = $action->handle(app('currentTenant'), $request->name, $request->email, $role);

        return back()->with([
            'success' => __('User created successfully.'),
            'createdUser' => [
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'password' => $result['password'],
            ],
        ]);
    }

    public function update(AssignRoleRequest $request, User $user, AssignRoleAction $action): RedirectResponse
    {
        $tenant = app('currentTenant');

        if ($request->has('role_id')) {
            $this->authorize('assignRole', User::class);
            $role = Role::withoutGlobalScopes()->findOrFail($request->role_id);
            $action->handle($tenant, $user, $role);
        }

        if ($request->has('is_active')) {
            $this->authorize('manage', $user);
            $tenant->users()->updateExistingPivot($user->id, [
                'is_active' => (bool) $request->is_active,
            ]);
        }

        return back()->with('success', __('Member updated successfully.'));
    }

    public function destroy(User $user, RemoveUserAction $action): RedirectResponse
    {
        $this->authorize('manage', $user);

        $action->handle(app('currentTenant'), $user, request()->user());

        return redirect()->route('users.index')->with('success', __('User removed from organization.'));
    }
}
