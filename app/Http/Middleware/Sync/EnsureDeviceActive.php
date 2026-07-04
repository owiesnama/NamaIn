<?php

namespace App\Http\Middleware\Sync;

use App\Models\Device;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Second link of the sync middleware chain (Design 02 §1.1): the principal
 * resolved by `auth:sync` must be an *active* Device. A revoked device gets the
 * first-class `403 device_revoked` status (the client wipes on it — Design 04
 * §4.2); anything else — a pending device, or a non-Device principal such as a
 * web user session leaking into the guard — is a plain 401.
 */
class EnsureDeviceActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->user('sync');

        if (! $device instanceof Device) {
            throw new AuthenticationException('Unauthenticated.', ['sync']);
        }

        if ($device->isRevoked()) {
            return response()->json(['error' => 'device_revoked'], 403);
        }

        if (! $device->isActive()) {
            throw new AuthenticationException('Unauthenticated.', ['sync']);
        }

        return $next($request);
    }
}
