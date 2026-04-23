<?php

namespace App\Policies;

use App\Models\Storage;
use App\Models\User;

class StoragePolicy
{
    /**
     * Determine whether the user can manage stock for the storage.
     */
    public function manageStock(User $user, Storage $storage): bool
    {
        return $user->hasRole('owner', 'manager');
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('owner', 'manager', 'cashier', 'staff');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Storage $storage): bool
    {
        return $user->hasRole('owner', 'manager', 'cashier', 'staff');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('owner', 'manager');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Storage $storage): bool
    {
        return $user->hasRole('owner', 'manager');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Storage $storage): bool
    {
        return $user->hasRole('owner', 'manager');
    }
}
