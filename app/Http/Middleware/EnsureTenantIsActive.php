<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): mixed
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if ($tenant && ! $tenant->isActive()) {
            if (session('impersonating_from')) {
                return redirect()->route('admin.dashboard')
                    ->with('error', __('This tenant is currently deactivated.'));
            }

            auth('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')
                ->with('error', __('Your account has been deactivated.'));
        }

        return $next($request);
    }
}
