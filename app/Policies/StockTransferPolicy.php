<?php

namespace App\Policies;

use App\Models\User;

class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.transfer');
    }

    public function view(User $user): bool
    {
        return $user->hasPermission('inventory.transfer');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.transfer');
    }
}
