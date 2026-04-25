<?php

namespace App\Policies;

use App\Models\User;

class AdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.manage');
    }

    public function view(User $user): bool
    {
        return $user->hasPermission('inventory.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.manage');
    }
}
