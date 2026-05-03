<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateTenantUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\TenantLoginRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TenantLoginController extends Controller
{
    public function show(): Response
    {
        $tenant = app('currentTenant');

        return Inertia::render('Auth/TenantLogin', [
            'tenant' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
        ]);
    }

    public function store(TenantLoginRequest $request, AuthenticateTenantUser $action): RedirectResponse
    {
        $tenant = app('currentTenant');

        $action->authenticate($request, $tenant);

        return redirect()->intended(
            tenant_route('dashboard', $tenant->slug),
        );
    }
}
