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
        return $user->hasPermission('inventory.manage');
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.view');
    }

    public function view(User $user, Storage $storage): bool
    {
        return $user->hasPermission('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.manage');
    }

    public function update(User $user, Storage $storage): bool
    {
        return $user->hasPermission('inventory.manage');
    }

    public function delete(User $user, Storage $storage): bool
    {
        return $user->hasPermission('inventory.manage');
    }
}
