<?php

namespace App\Queries;

use App\Models\StockMovement;
use Illuminate\Support\Collection;

/**
 * Derives stock balances from the append-only ledger (`stock_movements`).
 *
 * This is the authoritative balance per the target design. It is NOT a hot-path
 * read — `stocks.quantity` remains the O(1) cache used at POS speed. This query
 * exists for reconciliation (M2), opening balances (M9), and the eventual read
 * cutover (M10). All reads are tenant-scoped via StockMovement's global scope.
 */
class StockBalanceQuery
{
    /**
     * Ledger balance for a product across all its storages.
     */
    public function forProduct(int $productId): int
    {
        return (int) StockMovement::query()
            ->where('product_id', $productId)
            ->sum('quantity');
    }

    /**
     * Ledger balance for a product within a single storage.
     */
    public function forProductInStorage(int $productId, int $storageId): int
    {
        return (int) StockMovement::query()
            ->where('product_id', $productId)
            ->where('storage_id', $storageId)
            ->sum('quantity');
    }

    /**
     * Ledger balances grouped per (product, storage) for the current tenant.
     *
     * @return Collection<int, object{product_id: int, storage_id: int, balance: int}>
     */
    public function perProductStorage(): Collection
    {
        return StockMovement::query()
            ->selectRaw('product_id, storage_id, SUM(quantity) as balance')
            ->groupBy('product_id', 'storage_id')
            ->get()
            ->map(fn ($row) => (object) [
                'product_id' => (int) $row->product_id,
                'storage_id' => (int) $row->storage_id,
                'balance' => (int) $row->balance,
            ]);
    }
}
