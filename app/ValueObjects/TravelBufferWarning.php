<?php

namespace App\ValueObjects;

use App\Models\Booking;
use JsonSerializable;

/**
 * A soft warning that an on-site booking sits too close to a neighbouring
 * booking for the service's travel buffer. Never a hard block — the scheduler
 * may acknowledge it and proceed. `direction` is 'before' when the neighbour
 * precedes the candidate, 'after' when it follows.
 */
final class TravelBufferWarning implements JsonSerializable
{
    public function __construct(
        public readonly Booking $neighbor,
        public readonly int $gapMinutes,
        public readonly int $requiredBufferMinutes,
        public readonly string $direction,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'neighbor_id' => $this->neighbor->id,
            'gap_minutes' => $this->gapMinutes,
            'required_buffer_minutes' => $this->requiredBufferMinutes,
            'direction' => $this->direction,
        ];
    }
}
