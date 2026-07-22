<?php

namespace App\Http\Middleware\Sync;

use App\Services\Sync\SyncProtocol;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protocol versioning (Design 02 §8.4): clients send X-Sync-Protocol; when it
 * is below the server floor the answer is a first-class 426 upgrade_required
 * terminal status (the client stops syncing and prompts an update). A missing
 * header passes — the path already carries /v1.
 */
class EnsureSupportedProtocol
{
    public function handle(Request $request, Closure $next): Response
    {
        $protocol = $request->header(SyncProtocol::HEADER_PROTOCOL);

        if ($protocol !== null && (int) $protocol < SyncProtocol::MIN_PROTOCOL) {
            return response()->json([
                'error' => 'upgrade_required',
                'min_protocol' => SyncProtocol::MIN_PROTOCOL,
                'min_app_version' => SyncProtocol::MIN_APP_VERSION,
            ], 426);
        }

        return $next($request);
    }
}
