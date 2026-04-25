<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AcceptInvitationAction
{
    public function handle(UserInvitation $invitation, string $name, string $password): User
    {
        if ($invitation->isExpired()) {
            throw ValidationException::withMessages([
                'token' => __('This invitation has expired.'),
            ]);
        }

        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages([
                'token' => __('This invitation has already been accepted.'),
            ]);
        }

        $user = User::firstOrCreate(
            ['email' => $invitation->email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'current_tenant_id' => $invitation->tenant_id,
            ]
        );

        if (! $user->wasRecentlyCreated) {
            if (! $user->current_tenant_id) {
                $user->update(['current_tenant_id' => $invitation->tenant_id]);
            }
        }

        $invitation->tenant->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $invitation->role->slug,
                'role_id' => $invitation->role_id,
                'is_active' => true,
            ],
        ]);

        $invitation->update(['accepted_at' => now()]);

        return $user;
    }
}
