<?php

namespace App\Queries;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductShowQuery
{
    private Product $product;

    private array $filters;

    public function __construct(Product $product, array $filters = [])
    {
        $this->product = $product;
        $this->filters = $filters;
    }

    public function transactions(): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->latest()
            ->with(['invoice.invocable', 'unit', 'storage', 'invoice.transactions.product'])
            ->paginate(10)
            ->withQueryString();
    }

    public function stats(): array
    {
        $query = $this->baseQuery();

        return [
            'sales_count' => (float) (clone $query)->forCustomer()->sum('base_quantity'),
            'purchases_count' => (float) (clone $query)->forSupplier()->sum('base_quantity'),
            'current_stock' => $this->product->quantityOnHand(),
            'available_qty' => $this->product->availableQuantity(),
            'pending_sales' => $this->product->pendingSalesQuantity(),
            'pending_purchases' => $this->product->pendingPurchaseQuantity(),
        ];
    }

    public function chartData(): array
    {
        return Transaction::getMonthlyStats($this->product->id);
    }

    private function baseQuery(): Builder
    {
        return Transaction::where('product_id', $this->product->id)
            ->inDateRange($this->filters['from_date'] ?? null, $this->filters['to_date'] ?? null)
            ->forStorage($this->filters['storage_id'] ?? null)
            ->filterByType($this->filters['type'] ?? null);
    }
}
