<?php

namespace App\Actions\Users;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RemoveUserAction
{
    public function handle(Tenant $tenant, User $user, User $removedBy): void
    {
        if ($user->id === $removedBy->id) {
            throw ValidationException::withMessages([
                'user' => __('You cannot remove yourself from the organization.'),
            ]);
        }

        $role = $user->tenants()
            ->where('tenants.id', $tenant->id)
            ->first()
            ?->pivot
            ?->role;

        if ($role === 'owner') {
            throw ValidationException::withMessages([
                'user' => __('The owner cannot be removed from the organization.'),
            ]);
        }

        $tenant->users()->detach($user->id);

        if ($user->current_tenant_id === $tenant->id) {
            $user->update(['current_tenant_id' => null]);
        }
    }
}
