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
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->bound('currentTenant')) {
            $preferences = TenantCache::rememberForever('preferences', fn () => Preference::asPairs());
            App::setLocale($preferences['language'] ?? 'en');
        }

        return $next($request);
    }
}
