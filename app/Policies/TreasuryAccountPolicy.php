<?php

namespace App\Policies;

use App\Models\User;

class TreasuryAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('treasury.view');
    }

    public function view(User $user): bool
    {
        return $user->hasPermission('treasury.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('treasury.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('treasury.create');
    }

    public function transfer(User $user): bool
    {
        return $user->hasPermission('treasury.transfer');
    }

    public function adjust(User $user): bool
    {
        return $user->hasPermission('treasury.adjust');
    }
}
