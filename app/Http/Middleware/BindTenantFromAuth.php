<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class BindTenantFromAuth
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

        app()->instance('currentTenant', $tenant);

        return $next($request);
    }
}
