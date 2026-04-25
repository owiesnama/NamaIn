<?php

namespace App\Actions\Users;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ToggleUserStatusAction
{
    public function handle(Tenant $tenant, User $user): bool
    {
        $pivot = $user->tenants()
            ->where('tenants.id', $tenant->id)
            ->first()
            ?->pivot;

        if (! $pivot) {
            throw ValidationException::withMessages([
                'user' => __('User is not a member of this organization.'),
            ]);
        }

        $role = $user->tenants()
            ->where('tenants.id', $tenant->id)
            ->first()
            ?->pivot
            ?->role;

        if ($role === 'owner') {
            throw ValidationException::withMessages([
                'user' => __('The owner account cannot be disabled.'),
            ]);
        }

        $newStatus = ! $pivot->is_active;

        $tenant->users()->updateExistingPivot($user->id, ['is_active' => $newStatus]);

        return $newStatus;
    }
}
