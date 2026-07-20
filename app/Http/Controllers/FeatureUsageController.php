<?php

namespace App\Http\Controllers;

use App\Features\Facades\Entitlements;
use App\Features\Feature;
use Illuminate\Http\JsonResponse;
use ValueError;

/**
 * Returns a tenant's current usage and cap for a single limit feature.
 *
 * Kept out of the global shared props (which would run a COUNT per limit on
 * every request); pages that display "used / cap" fetch this lazily.
 */
class FeatureUsageController extends Controller
{
    public function show(string $feature): JsonResponse
    {
        try {
            $limitFeature = Feature::from($feature);
        } catch (ValueError) {
            abort(404);
        }

        abort_unless($limitFeature->isLimit(), 404);

        $entitlements = Entitlements::for(app('currentTenant'));

        return response()->json([
            'feature' => $limitFeature->value,
            'used' => $entitlements->usage($limitFeature),
            'limit' => $entitlements->limit($limitFeature),
            'remaining' => $entitlements->remaining($limitFeature),
        ]);
    }
}
