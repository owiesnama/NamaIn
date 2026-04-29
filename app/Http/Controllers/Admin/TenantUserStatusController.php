<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ToggleUserStatusAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class TenantUserStatusController extends Controller
{
    public function update(Tenant $tenant, User $user, ToggleUserStatusAction $action): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $newStatus = $action->handle($tenant, $user);
        $label = $newStatus ? __('activated') : __('deactivated');

        return back()->with('success', __('User :status successfully.', ['status' => $label]));
    }
}
