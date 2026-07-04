<?php

namespace App\Support;

/**
 * The single branching API over the runtime profile (Design 03 §2.2, seam S1).
 *
 * "cloud" is the multi-tenant web deployment; "local" is the single-tenant
 * offline desktop client. Nothing else may read config('runtime.profile')
 * directly — every profile branch must be greppable as `Runtime::`.
 */
final class Runtime
{
    public static function isLocal(): bool
    {
        return config('runtime.profile', 'cloud') === 'local';
    }

    public static function isCloud(): bool
    {
        return ! self::isLocal();
    }
}
