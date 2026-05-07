<?php

namespace App\Actions\Users;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\UserCredentialsNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResendCredentialsAction
{
    public function handle(Tenant $tenant, User $actor, User $target): void
    {
        if ($actor->id === $target->id) {
            throw ValidationException::withMessages([
                'user' => __('You cannot resend credentials to yourself.'),
            ]);
        }

        if (! $target->belongsToTenant($tenant)) {
            throw ValidationException::withMessages([
                'user' => __('This user is not a member of your organization.'),
            ]);
        }

        $pivot = $target->tenants()->where('tenants.id', $tenant->id)->first()?->pivot;

        if ($pivot?->role === 'owner') {
            throw ValidationException::withMessages([
                'user' => __('Cannot resend credentials to an owner.'),
            ]);
        }

        if (! $pivot?->is_active) {
            throw ValidationException::withMessages([
                'user' => __('Cannot resend credentials to an inactive user.'),
            ]);
        }

        if (! $target->must_change_password) {
            throw ValidationException::withMessages([
                'user' => __('This user has already set their own password.'),
            ]);
        }

        $temporaryPassword = Str::random(12);

        $target->password = Hash::make($temporaryPassword);
        $target->save();

        $target->notify(
            (new UserCredentialsNotification($tenant->name, $temporaryPassword))->locale(app()->getLocale())
        );
    }
}
