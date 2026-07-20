<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\ValueObjects\TravelBufferWarning;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Soft travel-buffer detection for on-site services. Surfaces a warning when a
 * neighbouring confirmed booking (preceding or following) sits closer than the
 * service's travel buffer — both directions independently, so a booking wedged
 * between two others can warn twice. Never throws and never blocks; the caller
 * decides how to surface and whether to proceed.
 */
class TravelBufferChecker
{
    /**
     * @return array<int, TravelBufferWarning>
     */
    public function warningsFor(Booking $candidate, ?int $ignoreId = null): array
    {
        $service = $candidate->service;

        if (! $service?->on_site) {
            return [];
        }

        $buffer = (int) ($service->travel_buffer_minutes ?? 0);

        if ($buffer <= 0) {
            return [];
        }

        $start = $candidate->starts_at;
        $end = $candidate->ends_at ?? $candidate->deriveEndsAt();

        $warnings = [];

        $preceding = $this->baseQuery($candidate, $ignoreId)
            ->where('ends_at', '<=', $start)
            ->orderByDesc('ends_at')
            ->first();

        if ($preceding && ($gap = $this->minutesBetween($preceding->ends_at, $start)) < $buffer) {
            $warnings[] = new TravelBufferWarning($preceding, $gap, $buffer, 'before');
        }

        $following = $this->baseQuery($candidate, $ignoreId)
            ->where('starts_at', '>=', $end)
            ->orderBy('starts_at')
            ->first();

        if ($following && ($gap = $this->minutesBetween($end, $following->starts_at)) < $buffer) {
            $warnings[] = new TravelBufferWarning($following, $gap, $buffer, 'after');
        }

        return $warnings;
    }

    /**
     * @return Builder<Booking>
     */
    private function baseQuery(Booking $candidate, ?int $ignoreId)
    {
        return Booking::query()
            ->confirmed()
            ->where('service_product_id', $candidate->service_product_id)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId));
    }

    /**
     * Whole minutes between two instants, computed on the underlying UTC
     * timestamps so it is timezone-correct regardless of the stored wall clock.
     */
    private function minutesBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return intdiv($to->getTimestamp() - $from->getTimestamp(), 60);
    }
}
