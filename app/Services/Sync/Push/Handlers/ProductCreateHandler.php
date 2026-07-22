<?php

namespace App\Services\Sync\Push\Handlers;

use App\Exceptions\Sync\RejectedMutation;
use App\Models\Category;
use App\Models\Device;
use App\Models\Product;
use App\Models\Storage;
use App\Models\User;
use App\Services\Sync\PublicIdResolver;
use App\Services\Sync\Push\MutationHandler;
use App\Services\Sync\Push\PushMutation;
use App\ValueObjects\Money;

/**
 * `product.create` (offline product authoring): replays a product minted on
 * the register — identity preset (product + unit public_ids stored verbatim),
 * categories attached by reference, and any initial quantity recorded as an
 * opening-balance ledger movement on the device's sale point.
 */
class ProductCreateHandler implements MutationHandler
{
    public function __construct(private PublicIdResolver $resolver) {}

    public function handle(PushMutation $mutation, User $actor, Device $device): array
    {
        $payload = $mutation->payload;

        if (blank($payload['name'] ?? null)) {
            throw RejectedMutation::validationFailed(__('A product needs a name.'));
        }

        $product = Product::create([
            'public_id' => $mutation->publicId,
            'name' => $payload['name'],
            'price' => Money::fromMinor((int) ($payload['price'] ?? 0))->major(),
            'cost' => Money::fromMinor((int) ($payload['cost'] ?? 0))->major(),
            'average_cost' => Money::fromMinor((int) ($payload['cost'] ?? 0))->major(),
            'currency' => $payload['currency'] ?? preference('currency', 'SDG'),
            'alert_quantity' => (int) ($payload['alert_quantity'] ?? 0),
        ]);

        foreach ($payload['units'] ?? [] as $unit) {
            $product->units()->create([
                'public_id' => $unit['public_id'] ?? null,
                'name' => $unit['name'],
                'conversion_factor' => (float) ($unit['conversion_factor'] ?? 1),
            ]);
        }

        $categoryIds = collect($payload['categories'] ?? [])
            ->map(fn (string $publicId) => $this->resolver->id(Category::class, $publicId))
            ->filter()
            ->all();

        if ($categoryIds !== []) {
            $product->categories()->sync($categoryIds);
        }

        $initial = (int) ($payload['initial_quantity'] ?? 0);

        if ($initial > 0) {
            $storage = $device->register->storage_id !== null
                ? Storage::withoutGlobalScopes()->find($device->register->storage_id)
                : null;

            $storage?->addStock($product, $initial, 'opening_balance', actor: $actor);
        }

        return ['public_id' => $product->public_id, 'serial' => null];
    }
}
