<?php

namespace App\Policies;

use App\Models\ReconciliationItem;
use App\Models\User;

/**
 * Gates the reconciliation inbox (Design 04 §3.1, R7). `owner` short-circuits
 * via Gate::before; `manager` inherits both slugs from DefaultRolesService.
 */
class ReconciliationItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('reconciliation.view');
    }

    public function view(User $user, ReconciliationItem $item): bool
    {
        return $user->hasPermission('reconciliation.view');
    }

    public function resolve(User $user, ReconciliationItem $item): bool
    {
        return $user->hasPermission('reconciliation.resolve');
    }
}
