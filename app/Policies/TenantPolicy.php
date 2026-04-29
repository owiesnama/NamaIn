<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin();
    }
}
