<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function invite(User $user): bool
    {
        return $user->hasPermission('users.invite');
    }

    public function manage(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false;
        }

        $tenant = app('currentTenant');
        $targetRole = $target->tenants()
            ->where('tenants.id', $tenant->id)
            ->first()
            ?->pivot
            ?->role;

        if ($targetRole === 'owner') {
            return false;
        }

        return $user->hasPermission('users.manage');
    }

    public function assignRole(User $user): bool
    {
        return $user->hasPermission('users.assign-role');
    }
}
