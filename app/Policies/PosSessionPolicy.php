<?php

namespace App\Policies;

use App\Models\User;

class PosSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pos.view');
    }

    public function view(User $user): bool
    {
        return $user->hasPermission('pos.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('pos.operate');
    }

    public function close(User $user): bool
    {
        return $user->hasPermission('pos.manage-sessions');
    }
}
