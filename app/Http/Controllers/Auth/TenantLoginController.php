<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateTenantUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\TenantLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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

    public function store(TenantLoginRequest $request, AuthenticateTenantUser $action): RedirectResponse|SymfonyResponse
    {
        $tenant = app('currentTenant');

        $user = $action->authenticate($request, $tenant);

        if (! $user->hasVerifiedEmail()) {
            $request->session()->put('verification_tenant', $tenant->slug);

            return Inertia::location(route('verification.notice'));
        }

        return redirect()->intended(
            tenant_route('dashboard', $tenant->slug),
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.login');
    }
}
