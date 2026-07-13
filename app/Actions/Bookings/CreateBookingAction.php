<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ServiceAddon;
use App\ValueObjects\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates a booking from a service, snapshotting the base price and each
 * selected add-on's name + price delta so later re-pricing of the service or
 * its add-ons never mutates historical bookings. This is the single choke
 * point for booking creation; the stored `total` is computed here once.
 */
class CreateBookingAction
{
    /**
     * @param  array<int>  $addonIds  ids of the service's own add-ons to snapshot
     *
     * @throws InvalidArgumentException when an add-on id does not belong to the service
     */
    public function handle(
        Product $service,
        Customer $customer,
        CarbonInterface $startsAt,
        array $addonIds = [],
        ?string $address = null,
        ?string $notes = null,
        BookingStatus $status = BookingStatus::Confirmed,
    ): Booking {
        return DB::transaction(function () use ($service, $customer, $startsAt, $addonIds, $address, $notes, $status) {
            $addons = $this->resolveAddons($service, $addonIds);

            $total = Money::fromMajor($service->price ?? 0);
            foreach ($addons as $addon) {
                $total = $total->add(Money::fromMajor($addon->price_delta ?? 0));
            }

            $booking = new Booking([
                'service_product_id' => $service->id,
                'customer_id' => $customer->id,
                'starts_at' => $startsAt,
                'status' => $status,
                'address' => $address,
                'notes' => $notes,
                'base_price' => $service->price ?? 0,
                'total' => $total->major(),
            ]);
            $booking->setRelation('service', $service);
            $booking->save();

            foreach ($addons as $addon) {
                $booking->addons()->create([
                    'service_addon_id' => $addon->id,
                    'name' => $addon->name,
                    'price_delta' => $addon->price_delta,
                ]);
            }

            return $booking->load('addons');
        });
    }

    /**
     * Load the requested add-ons scoped to the service, rejecting any id that
     * does not belong to it (cross-service / cross-tenant guard).
     *
     * @param  array<int>  $addonIds
     * @return Collection<int, ServiceAddon>
     */
    private function resolveAddons(Product $service, array $addonIds)
    {
        $addonIds = array_values(array_unique($addonIds));

        if ($addonIds === []) {
            return $service->serviceAddons()->whereRaw('1 = 0')->get();
        }

        $addons = $service->serviceAddons()->whereKey($addonIds)->get();

        if ($addons->count() !== count($addonIds)) {
            throw new InvalidArgumentException('One or more add-ons do not belong to the selected service.');
        }

        return $addons;
    }
}
