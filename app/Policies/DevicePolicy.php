<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

/**
 * Gates the device fleet (Design 04 §4, R7). Viewing needs `devices.view`;
 * enrolling/revoking/replacing needs `devices.manage`. Owner short-circuits via
 * Gate::before; manager inherits both slugs from DefaultRolesService.
 */
class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('devices.view');
    }

    public function view(User $user, Device $device): bool
    {
        return $user->hasPermission('devices.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('devices.manage');
    }
}
