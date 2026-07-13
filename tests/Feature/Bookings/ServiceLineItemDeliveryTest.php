<?php

use App\Actions\Stock\DeliverTransactionAction;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Transaction;
use App\Models\User;

test('delivering a service line item does not deduct stock or write a movement', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $service = Product::factory()->service()->create();
    $storage = Storage::factory()->create();
    $invoice = Invoice::factory()->create();
    $transaction = Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $service->id,
        'storage_id' => $storage->id,
        'base_quantity' => 5,
        'delivered' => false,
    ]);

    app(DeliverTransactionAction::class)->handle($transaction, $user);

    expect($transaction->fresh()->delivered)->toBeTrue();

    $this->assertDatabaseMissing('stock_movements', [
        'product_id' => $service->id,
        'reason' => 'sale_delivery',
    ]);
});
