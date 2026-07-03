<?php

namespace App\Actions;

use App\Models\ChangeLog;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

class UpdateQuoteAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Quote $quote, array $data): Quote
    {
        return DB::transaction(function () use ($quote, $data) {
            ChangeLog::lockTenant($quote->tenant_id);

            $quote->update([
                'customer_id' => $data['customer_id'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'discount' => $data['discount'] ?? 0,
            ]);

            // Per-model delete so each removed line emits a change-log tombstone.
            $quote->items->each->delete();

            $quote->items()->createMany(
                array_map(fn ($item) => [
                    'tenant_id' => $quote->tenant_id,
                    'product_id' => $item['product_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ], $data['items'])
            );

            return $quote->fresh();
        });
    }
}
