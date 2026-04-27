<?php

namespace App\Http\Middleware;

use App\Models\Preference;
use App\Services\TenantCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class HandleLocale
{
    /**
     * Handle an incoming request.
     *
     * Locale is set by ResolveTenant for tenant-scoped routes.
     * This middleware handles non-tenant requests (e.g. auth pages)
     * where the tenant is already bound by other means.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->bound('currentTenant') && ! App::isLocale(config('app.locale'))) {
            // Already set by ResolveTenant — nothing to do.
            return $next($request);
        }

        if (app()->bound('currentTenant')) {
            $preferences = TenantCache::rememberForever('preferences', fn () => Preference::asPairs());
            App::setLocale($preferences['language'] ?? config('app.locale'));
        }

        return $next($request);
    }
}
