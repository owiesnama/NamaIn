<?php

namespace App\Exceptions;

use App\Models\Booking;
use Exception;
use Illuminate\Support\Collection;

/**
 * Thrown when a new/edited booking's time range overlaps an existing confirmed
 * booking of the same service and the service does not permit overlaps. This is
 * a hard block — the caller must not persist the booking.
 */
class BookingOverlapException extends Exception
{
    /**
     * @param  Collection<int, Booking>  $conflicts
     */
    public function __construct(public Collection $conflicts)
    {
        parent::__construct('The selected time overlaps an existing booking for this service.');
    }
}
