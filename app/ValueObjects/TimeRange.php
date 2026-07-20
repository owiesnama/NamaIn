<?php

namespace App\ValueObjects;

use Carbon\CarbonInterface;

/**
 * A half-open time interval [start, end). Half-open is the load-bearing choice:
 * two bookings that exactly abut (one ends at the instant the next starts) do
 * NOT overlap, so back-to-back scheduling is allowed. The same boundary
 * operators (`<` end, `>` start) are mirrored by the SQL predicate in
 * OverlapDetector; TimeRangeParity asserts the two agree.
 */
final class TimeRange
{
    public function __construct(
        public readonly CarbonInterface $start,
        public readonly CarbonInterface $end,
    ) {}

    /**
     * Whether this range overlaps another under half-open semantics.
     */
    public function overlaps(self $other): bool
    {
        return $this->start->lessThan($other->end)
            && $this->end->greaterThan($other->start);
    }
}
