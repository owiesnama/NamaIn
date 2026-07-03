<?php

namespace App\Actions;

use App\Models\Quote;
use App\ValueObjects\Money;

class UpdateQuoteAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Quote $quote, array $data): Quote
    {
        $quote->update([
            'customer_id' => $data['customer_id'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'discount' => $data['discount'] ?? 0,
        ]);

        $quote->items()->delete();

        $quote->items()->insert(
            array_map(fn ($item) => [
                'tenant_id' => $quote->tenant_id,
                'quote_id' => $quote->id,
                'product_id' => $item['product_id'],
                'unit_id' => $item['unit_id'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => Money::fromMajor($item['unit_price'])->minor(),
                'created_at' => now(),
                'updated_at' => now(),
            ], $data['items'])
        );

        return $quote->fresh();
    }
}
