<?php

namespace App\Http\Responses;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        /** @var Request $request */
        $user = $request->user();
        $tenant = $this->resolveTenantFromHost($request);

        if ($tenant) {
            if (! $user->belongsToTenant($tenant)) {
                auth()->logout();
                $request->session()->invalidate();

                return redirect()->back()->withErrors([
                    'email' => __('You do not have access to this organization.'),
                ]);
            }

            $user->switchTenant($tenant);

            return Inertia::location(tenant_route('dashboard', $tenant->slug));
        }

        // Main domain login — redirect to tenant selector
        $tenants = $user->tenants;

        if ($tenants->count() === 1) {
            $single = $tenants->first();
            $user->switchTenant($single);

            return Inertia::location(tenant_route('dashboard', $single->slug));
        }

        return Inertia::location(route('tenants.select'));
    }

    private function resolveTenantFromHost(Request $request): ?Tenant
    {
        $appDomain = config('app.domain');
        $host = $request->getHost();

        if ($host === $appDomain || ! str_ends_with($host, '.' . $appDomain)) {
            return null;
        }

        $slug = str_replace('.' . $appDomain, '', $host);

        return Tenant::where('slug', $slug)->first();
    }
}
