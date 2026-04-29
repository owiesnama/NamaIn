<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class BindTenant
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (! $user || ! $user->current_tenant_id) {
            abort(401, 'Tenant context required.');
        }

        $tenant = Tenant::find($user->current_tenant_id);

        if (! $tenant) {
            abort(401, 'Tenant not found.');
        }

        if (! $user->belongsToTenant($tenant)) {
            abort(403, 'Unauthorized tenant access.');
        }

        if (! $tenant->isActive()) {
            abort(403, 'Tenant is deactivated.');
        }

        app()->instance('currentTenant', $tenant);

        return $next($request);
    }
}
