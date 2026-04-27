<?php

namespace App\Http\Controllers\Users;

use App\Actions\Users\ToggleUserStatusAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UserStatusController extends Controller
{
    public function update(User $user, ToggleUserStatusAction $action): RedirectResponse
    {
        $this->authorize('manage', $user);

        $action->handle(app('currentTenant'), $user);

        return back()->with('success', __('User status updated.'));
    }
}
