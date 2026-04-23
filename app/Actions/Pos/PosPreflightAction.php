<?php

namespace App\Actions\Pos;

use App\Models\PosSession;
use App\Models\Product;
use App\Models\Unit;

class PosPreflightAction
{
    public function __construct(
        private readonly FindReplenishmentSourceAction $replenishmentAction,
    ) {}

    /**
     * @param  array<int, array{product_id: int, quantity: float, unit_id?: int}>  $items
     * @return array<string, mixed>
     */
    public function execute(PosSession $session, array $items): array
    {
        $assessment = collect($items)
            ->map(fn (array $item) => $this->assessCartItem($session, $item))
            ->filter()
            ->reduce(fn (array $assessment, array $result) => $this->accumulateIntoAssessment($assessment, $result), [
                'transfers' => [],
                'unavailable' => [],
            ]);

        if (! empty($assessment['unavailable'])) {
            return [
                'requires_confirmation' => true,
                'unavailable_products' => $assessment['unavailable'],
                'success' => false,
                'message' => __('Some products are unavailable.'),
            ];
        }

        return [
            'requires_confirmation' => ! empty($assessment['transfers']),
            'transfers_required' => $assessment['transfers'],
        ];
    }

    /** @param array{product_id: int, quantity: float, unit_id?: int} $item */
    private function assessCartItem(PosSession $session, array $item): array
    {
        $product = Product::findOrFail($item['product_id']);
        $unit = isset($item['unit_id']) ? Unit::find($item['unit_id']) : null;
        $quantityRequested = $item['quantity'] * ($unit->conversion_factor ?? 1);
        $quantityAtSalePoint = $session->storage->quantityOf($product);

        if ($quantityAtSalePoint >= $quantityRequested) {
            return [];
        }

        $deficit = $quantityRequested - $quantityAtSalePoint;
        $replenishmentSource = $this->replenishmentAction->handle($product, $deficit);

        return $replenishmentSource
            ? ['transfer' => $this->stockTransferFor($product, $deficit, $replenishmentSource)]
            : ['unavailable' => $this->outOfStockEntryFor($product, $quantityRequested, $quantityAtSalePoint)];
    }

    private function stockTransferFor(Product $product, float $deficit, mixed $replenishmentSource): array
    {
        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $deficit,
            'from_warehouse_id' => $replenishmentSource->warehouse->id,
            'from_warehouse_name' => $replenishmentSource->warehouse->name,
            'warehouse_available' => $replenishmentSource->availableQuantity,
        ];
    }

    private function outOfStockEntryFor(Product $product, float $quantityRequested, float $quantityAtSalePoint): array
    {
        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'needed' => $quantityRequested,
            'available_locally' => $quantityAtSalePoint,
        ];
    }

    /**
     * @param  array{transfers: list<array>, unavailable: list<array>}  $assessment
     * @return array{transfers: list<array>, unavailable: list<array>}
     */
    private function accumulateIntoAssessment(array $assessment, array $result): array
    {
        if (isset($result['transfer'])) {
            $assessment['transfers'][] = $result['transfer'];
        } elseif (isset($result['unavailable'])) {
            $assessment['unavailable'][] = $result['unavailable'];
        }

        return $assessment;
    }
}
