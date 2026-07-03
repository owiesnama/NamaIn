<?php

namespace App\Services\Sync;

/**
 * The outcome of an idempotent mutation. `replayed` is false on the first,
 * write-producing run and true when a stored result was returned verbatim.
 */
class IdempotencyOutcome
{
    /**
     * @param  array<string, mixed>|null  $result
     */
    public function __construct(
        public readonly bool $replayed,
        public readonly string $status,
        public readonly ?string $resultPublicId,
        public readonly ?array $result,
    ) {}
}
