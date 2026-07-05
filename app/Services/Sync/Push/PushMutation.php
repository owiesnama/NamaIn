<?php

namespace App\Services\Sync\Push;

use App\Enums\MutationType;

/**
 * One inbound mutation from a push envelope (Design 02 §5.1), parsed into a
 * typed value object so handlers read intent, not array keys.
 */
final readonly class PushMutation
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $idempotencyKey,
        public MutationType $type,
        public string $publicId,
        public string $actorPublicId,
        public array $payload,
        public ?string $occurredAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            idempotencyKey: (string) $raw['idempotency_key'],
            type: MutationType::from($raw['type']),
            publicId: (string) $raw['public_id'],
            actorPublicId: (string) $raw['actor'],
            payload: $raw['payload'] ?? [],
            occurredAt: $raw['occurred_at'] ?? null,
        );
    }
}
