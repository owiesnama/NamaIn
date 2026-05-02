<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\StartImpersonationAction;
use App\Actions\Admin\StopImpersonationAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ImpersonationController extends Controller
{
    public function start(Tenant $tenant, User $user, StartImpersonationAction $action): RedirectResponse
    {
        $this->authorize('view', $tenant);

        $action->handle(request()->user(), $tenant, $user);

        session()->save();

        return redirect()->away(tenant_route('dashboard', $tenant->slug));
    }

    public function stop(StopImpersonationAction $action): RedirectResponse
    {
        $action->handle();

        return redirect()->route('admin.dashboard');
    }
}
