<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales.view') || $user->hasPermission('purchases.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('sales.view') || $user->hasPermission('purchases.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales.create') || $user->hasPermission('purchases.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('sales.create') || $user->hasPermission('purchases.create');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('sales.delete') || $user->hasPermission('purchases.delete');
    }
}
