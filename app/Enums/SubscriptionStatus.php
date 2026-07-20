<?php

namespace App\Enums;

/**
 * Subscription lifecycle states for v1 (entitlements-only, no billing engine).
 *
 * `past_due`/gateway states are intentionally omitted until billing lands.
 */
enum SubscriptionStatus: string
{
    case Active = 'active';
    case Trialing = 'trialing';
    case Canceled = 'canceled';

    /**
     * Statuses that grant entitlements (subject to expiry checks).
     *
     * @return array<int, self>
     */
    public static function live(): array
    {
        return [self::Active, self::Trialing];
    }
}
