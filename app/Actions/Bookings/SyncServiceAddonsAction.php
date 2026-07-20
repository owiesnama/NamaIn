<?php

namespace App\Actions\Bookings;

use App\Models\Product;

/**
 * Reconciles a service product's add-ons with the submitted list: existing
 * rows are updated, new rows created, and omitted rows soft-deleted. Historical
 * booking snapshots (`booking_addons`) are untouched — they hold their own
 * copies, so removing a source add-on never mutates a past booking.
 */
class SyncServiceAddonsAction
{
    /**
     * @param  array<int, array{id?: int|null, name: string, price_delta: numeric}>  $addons
     */
    public function handle(Product $service, array $addons): void
    {
        $keptIds = [];

        foreach ($addons as $row) {
            if (empty($row['name'])) {
                continue;
            }

            $addon = $service->serviceAddons()->updateOrCreate(
                ['id' => $row['id'] ?? null],
                ['name' => $row['name'], 'price_delta' => $row['price_delta'] ?? 0],
            );

            $keptIds[] = $addon->id;
        }

        $service->serviceAddons()->whereNotIn('id', $keptIds)->delete();
    }
}
