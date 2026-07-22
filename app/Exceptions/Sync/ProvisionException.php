<?php

namespace App\Exceptions\Sync;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Provisioning failures with their wire error codes (Design 02 §1.3 + §8.5).
 * Laravel renders the exception straight to the JSON error shape.
 */
class ProvisionException extends Exception
{
    private function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }

    public static function invalidPairingCode(): self
    {
        return new self(__('Invalid pairing code.'), 'invalid_pairing_code', 422);
    }

    public static function pairingExpired(): self
    {
        return new self(__('This pairing code has expired. Ask an administrator to enroll the device again.'), 'pairing_expired', 410);
    }

    public static function alreadyProvisioned(): self
    {
        return new self(__('This device has already been provisioned.'), 'already_provisioned', 409);
    }

    public static function offlineDisabled(): self
    {
        return new self(__('Offline mode is not enabled for this organization.'), 'offline_disabled', 403);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => $this->errorCode,
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
