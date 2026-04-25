<?php

namespace App\Actions\Users;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateDirectUserAction
{
    public function handle(Tenant $tenant, string $name, string $email, Role $role): array
    {
        if ($tenant->users()->where('users.email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('This user is already a member of your organization.'),
            ]);
        }

        $temporaryPassword = Str::random(12);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'email_verified_at' => now(),
                'current_tenant_id' => $tenant->id,
            ]
        );

        if (! $user->wasRecentlyCreated) {
            if ($user->belongsToTenant($tenant)) {
                throw ValidationException::withMessages([
                    'email' => __('This user is already a member of your organization.'),
                ]);
            }
        }

        $tenant->users()->attach($user->id, [
            'role' => $role->slug,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        return ['user' => $user, 'password' => $temporaryPassword];
    }
}
