<?php

namespace App\Policies;

use App\Models\User;

class StoragePolicy
{
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user): bool
    {
        return $user->isAdmin();
    }
}
