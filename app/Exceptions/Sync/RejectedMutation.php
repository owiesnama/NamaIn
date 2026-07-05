<?php

namespace App\Exceptions\Sync;

use App\Enums\RejectionReason;
use RuntimeException;

/**
 * Thrown by a push mutation handler to reject a single mutation (Design 02
 * §5.2). Thrown inside the per-mutation transaction so the rollback leaves zero
 * writes and — for a retriable {@see RejectionReason::UnknownReference} — no
 * idempotency row, letting the device re-push once the missing parent lands.
 */
class RejectedMutation extends RuntimeException
{
    public function __construct(
        public readonly RejectionReason $reason,
        ?string $message = null,
    ) {
        parent::__construct($message ?? $reason->message());
    }

    public static function unknownReference(?string $message = null): self
    {
        return new self(RejectionReason::UnknownReference, $message);
    }

    public static function sessionClosed(?string $message = null): self
    {
        return new self(RejectionReason::SessionClosed, $message);
    }

    public static function tenantMismatch(?string $message = null): self
    {
        return new self(RejectionReason::TenantMismatch, $message);
    }

    public static function validationFailed(?string $message = null): self
    {
        return new self(RejectionReason::ValidationFailed, $message);
    }
}
