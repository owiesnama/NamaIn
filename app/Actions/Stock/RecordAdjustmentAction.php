<?php

namespace App\Actions\Stock;

use App\Models\Adjustment;
use App\Models\Product;
use App\Models\Storage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordAdjustmentAction
{
    public function execute(Storage $storage, Product $product, int $newQuantity, string $type, User $actor, ?string $notes = null): Adjustment
    {
        return DB::transaction(function () use ($storage, $product, $newQuantity, $type, $actor, $notes) {
            $quantityBefore = $storage->quantityOf($product);

            $adjustment = Adjustment::create([
                'tenant_id' => $storage->tenant_id,
                'storage_id' => $storage->id,
                'product_id' => $product->id,
                'created_by' => $actor->id,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $newQuantity,
                'type' => $type,
                'notes' => $notes,
            ]);

            $storage->setStockTo(
                product: $product,
                quantity: $newQuantity,
                reason: 'adjustment',
                movable: $adjustment,
                actor: $actor
            );

            return $adjustment;
        });
    }
}
