<?php

namespace App\Services\Bookings;

use App\Exceptions\BookingOverlapException;
use App\Models\Booking;
use App\ValueObjects\TimeRange;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Hard-block overlap detection, scoped per-service. A candidate booking is
 * checked only against other CONFIRMED bookings of the same service; cancelled
 * and completed bookings never constrain placement, so cancelling a booking
 * immediately frees its slot. When the service permits overlaps the check is a
 * no-op.
 */
class OverlapDetector
{
    /**
     * @throws BookingOverlapException when the candidate collides and the service disallows overlap
     */
    public function assertNoConflict(Booking $candidate, ?int $ignoreId = null): void
    {
        if ($candidate->service?->allow_overlap) {
            return;
        }

        $conflicts = $this->conflicts($candidate, $ignoreId);

        if ($conflicts->isNotEmpty()) {
            throw new BookingOverlapException($conflicts);
        }
    }

    /**
     * Confirmed, same-service bookings whose stored interval overlaps the
     * candidate under half-open semantics (`starts_at < end AND ends_at > start`),
     * mirroring TimeRange::overlaps. `$ignoreId` excludes the row being edited.
     *
     * @return Collection<int, Booking>
     */
    public function conflicts(Booking $candidate, ?int $ignoreId = null): Collection
    {
        [$start, $end] = $this->interval($candidate);

        return Booking::query()
            ->confirmed()
            ->where('service_product_id', $candidate->service_product_id)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->get();
    }

    /**
     * Resolve the candidate's [start, end) interval, deriving the end from the
     * service duration when the candidate has not yet been persisted.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function interval(Booking $candidate): array
    {
        $range = new TimeRange(
            $candidate->starts_at,
            $candidate->ends_at ?? $candidate->deriveEndsAt(),
        );

        return [$range->start, $range->end];
    }
}
