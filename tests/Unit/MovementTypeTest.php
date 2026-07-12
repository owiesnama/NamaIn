<?php

use App\Enums\MovementType;

test('fromReason resolves each runtime reason to its exact type', function (string $reason, MovementType $expected) {
    expect(MovementType::fromReason($reason))->toBe($expected);
})->with([
    ['opening_balance', MovementType::OpeningBalance],
    ['purchase_receipt', MovementType::PurchaseReceipt],
    ['invoice_addition', MovementType::InvoiceAddition],
    ['invoice_deduction', MovementType::InvoiceDeduction],
    ['sale_delivery', MovementType::SaleDelivery],
    ['adjustment', MovementType::Adjustment],
    ['transfer_in', MovementType::TransferIn],
    ['transfer_out', MovementType::TransferOut],
    ['sales_return', MovementType::SalesReturn],
    ['purchase_return', MovementType::PurchaseReturn],
]);

test('fromReason falls back to Adjustment for unknown or legacy reasons', function (string $reason) {
    expect(MovementType::fromReason($reason))->toBe(MovementType::Adjustment);
})->with(['restock', 'initial_stock', 'sale', 'manual_adjustment', 'transfer', '']);

test('isIncrease reflects the direction of the movement type', function () {
    expect(MovementType::PurchaseReceipt->isIncrease())->toBeTrue();
    expect(MovementType::OpeningBalance->isIncrease())->toBeTrue();
    expect(MovementType::TransferIn->isIncrease())->toBeTrue();
    expect(MovementType::SaleDelivery->isIncrease())->toBeFalse();
    expect(MovementType::TransferOut->isIncrease())->toBeFalse();
    expect(MovementType::PurchaseReturn->isIncrease())->toBeFalse();
});
