<?php

namespace App\Services\Sync;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Coarse clock-skew estimate (Design 04 §5.1, R12): `server_now − client_time`
 * in seconds, read from the device's `X-Client-Time` header. The round trip is
 * unobservable server-side, so the estimate is deliberately generous; a device
 * whose |skew| exceeds the threshold (§4.1) is flagged `skewed`. Returns null
 * when the header is absent or unparseable, so nothing is overwritten.
 */
final class ClockSkew
{
    public static function fromRequest(Request $request): ?int
    {
        $clientTime = $request->header('X-Client-Time');

        if (! is_string($clientTime) || $clientTime === '') {
            return null;
        }

        try {
            $client = CarbonImmutable::parse($clientTime);
        } catch (\Throwable) {
            return null;
        }

        return CarbonImmutable::now()->getTimestamp() - $client->getTimestamp();
    }
}
