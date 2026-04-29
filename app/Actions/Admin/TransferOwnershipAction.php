<?php

namespace App\Actions\Admin;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TransferOwnershipAction
{
    public function handle(Tenant $tenant, User $newOwner): void
    {
        if (! $newOwner->belongsToTenant($tenant)) {
            throw ValidationException::withMessages([
                'user' => __('User is not a member of this organization.'),
            ]);
        }

        $currentOwner = $tenant->owner();
        $ownerRole = Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'owner')
            ->firstOrFail();

        $managerRole = Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'manager')
            ->firstOrFail();

        if ($currentOwner) {
            $tenant->users()->updateExistingPivot($currentOwner->id, [
                'role' => 'manager',
                'role_id' => $managerRole->id,
            ]);
        }

        $tenant->users()->updateExistingPivot($newOwner->id, [
            'role' => 'owner',
            'role_id' => $ownerRole->id,
        ]);
    }
}
