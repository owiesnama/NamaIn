<?php

namespace App\Services\Bookings;

use App\Exceptions\BookingOverlapException;
use App\Models\Booking;
use App\ValueObjects\TravelBufferWarning;

/**
 * The single seam B3 consumes: assert the hard rules (overlap) first, then
 * return any soft travel-buffer warnings for the caller to surface. A thrown
 * BookingOverlapException means the booking must not be saved; a non-empty
 * return is advisory only.
 */
class BookingScheduler
{
    public function __construct(
        private OverlapDetector $overlapDetector,
        private TravelBufferChecker $travelBufferChecker,
    ) {}

    /**
     * @return array<int, TravelBufferWarning>
     *
     * @throws BookingOverlapException
     */
    public function assertBookable(Booking $candidate, ?int $ignoreId = null): array
    {
        $this->overlapDetector->assertNoConflict($candidate, $ignoreId);

        return $this->travelBufferChecker->warningsFor($candidate, $ignoreId);
    }
}
