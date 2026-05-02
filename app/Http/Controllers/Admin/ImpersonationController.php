<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\StartImpersonationAction;
use App\Actions\Admin\StopImpersonationAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ImpersonationController extends Controller
{
    public function start(Tenant $tenant, User $user, StartImpersonationAction $action): Response
    {
        $this->authorize('view', $tenant);

        $action->handle(request()->user(), $tenant, $user);

        session()->save();

        return Inertia::location(tenant_route('dashboard', $tenant->slug));
    }

    public function stop(StopImpersonationAction $action): Response
    {
        $action->handle();

        session()->save();

        return Inertia::location(route('admin.dashboard'));
    }
}
