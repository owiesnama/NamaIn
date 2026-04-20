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
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user): bool
    {
        return $user->isAdmin();
    }
}
