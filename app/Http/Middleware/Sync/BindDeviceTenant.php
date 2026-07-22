<?php

namespace App\Http\Middleware\Sync;

use App\Models\Device;
use App\Models\Preference;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Third link of the sync middleware chain (Design 02 §1.1): the tenant comes
 * from the device row — never from the request — so cross-tenant reads are
 * impossible and every downstream query is TenantScope-bound (failing closed
 * to `1 = 0` if the binding ever unsets). Locale mirrors
 * GenerateExportJob::bindTenantContext. `last_seen_at` is written quietly:
 * device health beats must not spam the change log.
 */
class BindDeviceTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Device $device */
        $device = $request->user('sync');
        $tenant = $device->tenant;

        app()->instance('currentTenant', $tenant);

        // Every syncable write in this request is attributed to the device that
        // pushed it: ChangeLog::record stamps source_device_id from this binding
        // so other devices can tell a pushed row from a cloud-web one (§8.2).
        app()->instance('currentDevice', $device);

        $preferences = Preference::where('tenant_id', $tenant->id)->pluck('value', 'key');
        app()->setLocale($preferences['language'] ?? config('app.locale'));

        $device->forceFill(['last_seen_at' => now()])->saveQuietly();

        return $next($request);
    }
}
