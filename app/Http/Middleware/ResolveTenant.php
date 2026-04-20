<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
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

        if (auth()->check() && auth()->user()->current_tenant_id !== $tenant->id) {
            if (auth()->user()->belongsToTenant($tenant)) {
                auth()->user()->switchTenant($tenant);
            }
        }

        $request->route()->forgetParameter('tenant');

        return $next($request);
    }
}
