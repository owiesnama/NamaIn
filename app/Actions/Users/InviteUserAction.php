<?php

namespace App\Actions\Users;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteUserAction
{
    public function handle(Tenant $tenant, string $email, Role $role, User $invitedBy): UserInvitation
    {
        if ($tenant->users()->where('users.email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('This user is already a member of your organization.'),
            ]);
        }

        UserInvitation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->pending()
            ->delete();

        $invitation = UserInvitation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'invited_by' => $invitedBy->id,
            'role_id' => $role->id,
            'email' => $email,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new UserInvitedNotification($invitation));

        return $invitation;
    }
}
