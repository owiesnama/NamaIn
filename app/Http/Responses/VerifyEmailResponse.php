<?php

namespace App\Http\Responses;

use App\Models\Tenant;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse($request): Response
    {
        $user = $request->user();
        $tenantSlug = $request->session()->pull('verification_tenant');

        if ($tenantSlug) {
            $tenant = Tenant::where('slug', $tenantSlug)->first();

            if ($tenant && $user->belongsToTenant($tenant)) {
                $user->switchTenant($tenant);

                return Inertia::location(tenant_route('dashboard', $tenantSlug));
            }
        }

        // Fallback: mirror LoginResponse tenant routing
        $tenants = $user->tenants;

        if ($tenants->count() === 1) {
            $single = $tenants->first();
            $user->switchTenant($single);

            return Inertia::location(tenant_route('dashboard', $single->slug));
        }

        return Inertia::location(route('tenants.select'));
    }
}
