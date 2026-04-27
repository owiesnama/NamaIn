<?php

namespace App\Policies;

use App\Models\Cheque;
use App\Models\User;

class ChequePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payments.manage-cheques');
    }

    public function view(User $user, Cheque $cheque): bool
    {
        return $user->hasPermission('payments.manage-cheques');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('payments.manage-cheques');
    }

    public function update(User $user, Cheque $cheque): bool
    {
        return $user->hasPermission('payments.manage-cheques');
    }

    public function delete(User $user, Cheque $cheque): bool
    {
        return $user->hasPermission('payments.manage-cheques');
    }
}
