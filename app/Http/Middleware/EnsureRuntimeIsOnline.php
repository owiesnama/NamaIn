<?php

namespace App\Http\Middleware;

use App\Support\Runtime;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates online-only route groups (Design 03 §2.2, seam S4). Under the local
 * runtime profile these routes 404 — hidden, not broken — so the offline
 * client never exposes surfaces that need the cloud to function.
 */
class EnsureRuntimeIsOnline
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Runtime::isCloud(), 404);

        return $next($request);
    }
}
