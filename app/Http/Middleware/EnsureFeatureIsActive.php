<?php

namespace App\Http\Middleware;

use App\Features\Facades\Entitlements;
use App\Features\Feature;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a route unless the current tenant's plan entitles the given boolean
 * feature(s). Usage: `->middleware('feature:quotes')` (composes with `can:`).
 *
 * Failure behavior:
 *   - full-page GET  → an Inertia "Upgrade" page (403)
 *   - everything else (JSON, non-GET, Inertia partial reloads) → 403
 */
class EnsureFeatureIsActive
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        foreach ($features as $key) {
            $feature = Feature::from($key);

            if (! $feature->isBoolean()) {
                throw new InvalidArgumentException(
                    "The feature middleware only gates boolean features; [{$key}] is a limit."
                );
            }

            if (! Entitlements::enabled($feature)) {
                return $this->deny($request, $feature);
            }
        }

        return $next($request);
    }

    private function deny(Request $request, Feature $feature): Response
    {
        $wantsPage = $request->isMethod('GET')
            && ! $request->expectsJson()
            && ! $request->headers->has('X-Inertia-Partial-Data');

        if (! $wantsPage) {
            abort(403, __('entitlements.locked', ['feature' => __($feature->labelKey())]));
        }

        return Inertia::render('Upgrade', [
            'feature' => __($feature->labelKey()),
            'planName' => app('currentTenant')->activePlan()?->displayName(),
        ])->toResponse($request)->setStatusCode(403);
    }
}
