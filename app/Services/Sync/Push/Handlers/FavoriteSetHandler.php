<?php

namespace App\Services\Sync\Push\Handlers;

use App\Exceptions\Sync\RejectedMutation;
use App\Models\Device;
use App\Models\Product;
use App\Models\User;
use App\Services\Sync\PublicIdResolver;
use App\Services\Sync\Push\MutationHandler;
use App\Services\Sync\Push\PushMutation;

/**
 * `favorite.set`: toggles the acting cashier's personal favorite for a
 * product. Idempotent by construction (sync/detach), so replays are no-ops.
 */
class FavoriteSetHandler implements MutationHandler
{
    public function __construct(private PublicIdResolver $resolver) {}

    public function handle(PushMutation $mutation, User $actor, Device $device): array
    {
        $productId = $this->resolver->id(Product::class, $mutation->payload['product'] ?? null);

        if ($productId === null) {
            throw RejectedMutation::unknownReference(__('The product for this favorite has not synced yet.'));
        }

        $favorite = filter_var($mutation->payload['favorite'] ?? true, FILTER_VALIDATE_BOOL);
        $relation = $actor->belongsToMany(Product::class, 'favorite_products')->withTimestamps();

        $favorite
            ? $relation->syncWithoutDetaching([$productId])
            : $relation->detach($productId);

        return ['public_id' => $mutation->publicId, 'serial' => null];
    }
}
