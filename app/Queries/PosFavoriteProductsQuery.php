<?php

namespace App\Queries;

use App\Models\Product;
use App\Models\Storage;
use Closure;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the ordered POS favourites list by merging two tiers — the current
 * user's personal favourites and the tenant-wide global favourites — into a
 * single, de-duplicated, availability-first list for one sale point.
 *
 * Ordering (highest priority first):
 *   1. tier: user favourites, then global favourites
 *   2. availability: available (in stock at the sale point), then unavailable
 *   3. name (ascending)
 *
 * A product that is both a user favourite and a global favourite appears once,
 * in the user tier. The set is bounded (a cashier's stars + tenant globals), so
 * the final tier/availability/name ordering is done in PHP; the underlying query
 * still reads stock from the indexed sale-point join.
 */
class PosFavoriteProductsQuery
{
    /**
     * @param  array<int, int>  $userFavoriteIds  Product ids the current user has starred.
     */
    public function __construct(
        private readonly Storage $storage,
        private readonly array $userFavoriteIds,
    ) {}

    /**
     * @return Collection<int, Product>
     */
    public function get(): Collection
    {
        $products = Product::query()
            ->with('units')
            ->leftJoin('stocks', function ($join) {
                $join->on('stocks.product_id', '=', 'products.id')
                    ->where('stocks.storage_id', $this->storage->id)
                    ->whereNull('stocks.deleted_at');
            })
            ->select('products.*', 'stocks.quantity as sale_point_qty')
            ->where(function ($query) {
                $query->whereIn('products.id', $this->userFavoriteIds)
                    ->orWhere('products.is_global_favorite', true);
            })
            ->get();

        $userFavoriteIndex = array_flip($this->userFavoriteIds);

        return $products
            ->each(function (Product $product) use ($userFavoriteIndex) {
                $product->favorite_scope = isset($userFavoriteIndex[$product->id]) ? 'user' : 'global';
            })
            ->sort($this->ordering($userFavoriteIndex))
            ->values();
    }

    /**
     * @param  array<int, int>  $userFavoriteIndex
     */
    private function ordering(array $userFavoriteIndex): Closure
    {
        return function (Product $a, Product $b) use ($userFavoriteIndex) {
            $tier = $this->tier($a, $userFavoriteIndex) <=> $this->tier($b, $userFavoriteIndex);

            if ($tier !== 0) {
                return $tier;
            }

            $availability = $this->availabilityRank($a) <=> $this->availabilityRank($b);

            if ($availability !== 0) {
                return $availability;
            }

            return strcmp((string) $a->name, (string) $b->name);
        };
    }

    /**
     * @param  array<int, int>  $userFavoriteIndex
     */
    private function tier(Product $product, array $userFavoriteIndex): int
    {
        return isset($userFavoriteIndex[$product->id]) ? 0 : 1;
    }

    /**
     * Available (in stock at this sale point) ranks before unavailable.
     */
    private function availabilityRank(Product $product): int
    {
        return ((int) ($product->sale_point_qty ?? 0)) > 0 ? 0 : 1;
    }
}
