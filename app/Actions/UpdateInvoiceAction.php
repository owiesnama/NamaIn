<?php

namespace App\Actions;

use App\Models\ChangeLog;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdateInvoiceAction
{
    public function handle(Invoice $invoice, Collection $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            ChangeLog::lockTenant($invoice->tenant_id);

            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            if (! $invoice->isEditable()) {
                throw new ConflictHttpException('Invoice can no longer be edited.');
            }

            $invocable = $data->get('invocable');
            $products = collect($data->get('products'));
            $total = $products->sum(fn ($product) => (float) $product['price'] * (float) $product['quantity']);

            $invoice->fill([
                'invocable_id' => $invocable['id'],
                'invocable_type' => $invocable['type'],
                'total' => $total,
                'discount' => $data->get('discount', 0),
                'payment_method' => $data->get('payment_method'),
            ])->save();

            $this->replaceTransactions($invoice, $data);

            $invoice->updatePaymentStatus();

            return $invoice->fresh();
        });
    }

    private function replaceTransactions(Invoice $invoice, Collection $data): void
    {
        // Per-model delete so each removed transaction emits a change-log tombstone.
        $invoice->transactions->each->delete();

        $products = collect($data->get('products'));
        $units = Unit::whereIn('id', $products->pluck('unit')->filter()->unique())
            ->get()
            ->keyBy('id');

        $isSale = $invoice->isSale();
        $averageCosts = $isSale
            ? Product::whereIn('id', $products->pluck('product')->unique())->get()->keyBy('id')
            : collect();

        $invoice->transactions()->createMany($products->map(fn ($product) => [
            'product_id' => $product['product'],
            'storage_id' => $product['storage'] ?? null,
            'unit_id' => $product['unit'] ?? null,
            'quantity' => $product['quantity'],
            'price' => $product['price'],
            'description' => $product['description'] ?? null,
            'unit_cost' => $isSale
                ? ($averageCosts[$product['product']]->average_cost ?? 0)
                : null,
            'cost_provisional' => $isSale
                ? (($averageCosts[$product['product']]->average_cost ?? 0) <= 0)
                : false,
            'base_quantity' => isset($units[$product['unit'] ?? null])
                ? $units[$product['unit']]->conversion_factor * $product['quantity']
                : $product['quantity'],
        ])->all());
    }
}
