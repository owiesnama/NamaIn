<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        /** @var Request $request */
        $user = $request->user();

        // If arriving via subdomain, the tenant is already resolved
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if ($tenant) {
            if (! $user->belongsToTenant($tenant)) {
                auth()->logout();
                $request->session()->invalidate();

                return redirect()->back()->withErrors([
                    'email' => __('You do not have access to this organization.'),
                ]);
            }

            $user->switchTenant($tenant);

            return $request->wantsJson()
                ? new JsonResponse(['two_factor' => false])
                : redirect()->intended('/dashboard');
        }

        // Main domain login — redirect to tenant selector
        $tenants = $user->tenants;

        if ($tenants->count() === 1) {
            $single = $tenants->first();
            $user->switchTenant($single);

            return redirect()->away(tenant_route('dashboard', $single->slug));
        }

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false])
            : redirect()->route('tenants.select');
    }
}
