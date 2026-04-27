<?php

namespace App\Http\Middleware;

use App\Models\Preference;
use App\Models\Tenant;
use App\Services\TenantCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): mixed
    {
        $slug = $request->route('tenant');

        if (! $slug) {
            return $next($request);
        }

        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            throw new NotFoundHttpException('Tenant not found.');
        }

        app()->instance('currentTenant', $tenant);
        URL::defaults(['tenant' => $slug]);

        $preferences = TenantCache::rememberForever('preferences', fn () => Preference::asPairs());
        App::setLocale($preferences['language'] ?? config('app.locale'));

        if (auth()->check() && ! auth()->user()->belongsToTenant($tenant)) {
            abort(403, 'You do not have access to this tenant.');
        }

        $request->route()->forgetParameter('tenant');

        return $next($request);
    }
}
