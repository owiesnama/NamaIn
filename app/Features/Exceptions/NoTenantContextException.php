<?php

namespace App\Features\Exceptions;

use RuntimeException;

/**
 * Thrown when an ambient entitlement check is made with no resolvable tenant.
 *
 * We fail loud rather than silently resolving every feature to "off", so that
 * a missing tenant binding in a new command/job surfaces immediately.
 */
class NoTenantContextException extends RuntimeException
{
    public static function make(): self
    {
        return new self('No tenant context is available to resolve entitlements. Use Entitlements::for($tenant) with an explicit tenant.');
    }
}
