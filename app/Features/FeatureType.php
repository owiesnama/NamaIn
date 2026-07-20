<?php

namespace App\Features;

/**
 * The two kinds of gateable feature.
 *
 * - Boolean: a capability that is simply on or off for a tenant.
 * - Limit: a numeric quota (a cap); `null` means unlimited.
 */
enum FeatureType: string
{
    case Boolean = 'boolean';
    case Limit = 'limit';
}
