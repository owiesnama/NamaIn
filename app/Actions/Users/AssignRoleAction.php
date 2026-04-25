<?php

namespace App\Actions\Users;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AssignRoleAction
{
    public function handle(Tenant $tenant, User $user, Role $role): void
    {
        $currentRole = $user->tenants()
            ->where('tenants.id', $tenant->id)
            ->first()
            ?->pivot
            ?->role;

        if ($currentRole === 'owner') {
            throw ValidationException::withMessages([
                'role' => __('The owner role cannot be changed.'),
            ]);
        }

        if ($role->slug === 'owner') {
            throw ValidationException::withMessages([
                'role' => __('The owner role cannot be assigned.'),
            ]);
        }

        $tenant->users()->updateExistingPivot($user->id, [
            'role' => $role->slug,
            'role_id' => $role->id,
        ]);

        $user->unsetRelation('tenants');
    }
}
