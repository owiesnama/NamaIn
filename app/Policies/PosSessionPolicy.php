<?php

namespace App\Policies;

use App\Models\User;

class PosSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('owner', 'manager', 'cashier');
    }

    public function view(User $user): bool
    {
        return $user->hasRole('owner', 'manager', 'cashier');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('owner', 'manager', 'cashier');
    }

    public function close(User $user): bool
    {
        return $user->hasRole('owner', 'manager', 'cashier');
    }
}
