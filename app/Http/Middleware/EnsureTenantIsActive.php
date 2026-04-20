<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): mixed
    {
        $tenant = auth()->user()?->currentTenant;

        if ($tenant && ! $tenant->isActive()) {
            auth()->logout();
            $request->session()->invalidate();

            return redirect()->route('login')
                ->with('error', __('Your account has been deactivated.'));
        }

        return $next($request);
    }
}
