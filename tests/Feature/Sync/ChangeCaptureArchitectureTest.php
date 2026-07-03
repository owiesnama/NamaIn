<?php

use Symfony\Component\Finder\Finder;

/**
 * Enforces the change-capture guarantee (Design 01 §4.2): no syncable table may
 * be mutated through the raw query builder outside the explicit Channel-B
 * allow-list, and no relationship raw insert may bypass model events. This turns
 * "we believe nothing escapes the change log" into a build-time invariant.
 */
$syncableTables = [
    'invoices', 'transactions', 'transaction_receipts', 'payments', 'customers',
    'customer_advances', 'suppliers', 'products', 'units', 'categories', 'stocks',
    'stock_movements', 'stock_transfers', 'stock_transfer_lines', 'adjustments',
    'storages', 'pos_sessions', 'treasury_accounts', 'treasury_movements',
    'treasury_transfers', 'cheques', 'banks', 'expenses', 'recurring_expenses',
    'quotes', 'quote_items', 'preferences',
];

// The only files permitted to raw-mutate a syncable table, each paired with an
// adjacent ChangeLog::record (Channel B).
$rawWriteAllowList = [
    'app/Models/Storage.php',  // stocks
    'app/Models/Product.php',  // products.average_cost
];

function syncSourceFiles(): array
{
    $files = [];
    foreach (Finder::create()->files()->in(base_path('app'))->name('*.php') as $file) {
        $files[] = [
            'relative' => str_replace(base_path().'/', '', $file->getRealPath()),
            'contents' => $file->getContents(),
        ];
    }

    return $files;
}

it('never raw-mutates a syncable table outside the Channel-B allow-list', function () use ($syncableTables, $rawWriteAllowList) {
    $mutators = 'insert|insertOrIgnore|insertGetId|update|delete|increment|decrement|upsert';
    $pattern = "/DB::table\(\s*['\"](".implode('|', $syncableTables).")['\"]\s*\)\s*->\s*($mutators)\b/";

    $violations = [];
    foreach (syncSourceFiles() as $file) {
        if (in_array($file['relative'], $rawWriteAllowList, true)) {
            continue;
        }
        if (preg_match($pattern, $file['contents'])) {
            $violations[] = $file['relative'];
        }
    }

    expect($violations)->toBe([]);
});

it('never inserts through a relationship (which bypasses model events)', function () {
    $violations = [];
    foreach (syncSourceFiles() as $file) {
        foreach (explode("\n", $file['contents']) as $number => $line) {
            if (str_contains($line, 'DB::table')) {
                continue;
            }
            if (preg_match('/->\s*(insert|insertOrIgnore)\s*\(/', $line)) {
                $violations[] = $file['relative'].':'.($number + 1);
            }
        }
    }

    expect($violations)->toBe([]);
});

it('never deletes through a relationship query builder', function () {
    $violations = [];
    foreach (syncSourceFiles() as $file) {
        foreach (explode("\n", $file['contents']) as $number => $line) {
            // `$model->relation()->delete()` bypasses events; `$model->delete()`
            // and `$collection->each->delete()` do not and are fine.
            if (preg_match('/\)\s*->\s*delete\s*\(\s*\)/', $line)) {
                $violations[] = $file['relative'].':'.($number + 1);
            }
        }
    }

    expect($violations)->toBe([]);
});

it('locks the tenant change counter first in every syncable-write transaction', function () {
    $mustLock = [
        'app/Actions/Pos/ProcessPosCheckoutAction.php',
        'app/Actions/StoreInvoiceAction.php',
        'app/Actions/UpdateInvoiceAction.php',
        'app/Actions/CreateInverseInvoiceAction.php',
        'app/Actions/StoreQuoteAction.php',
        'app/Actions/UpdateQuoteAction.php',
        'app/Actions/UpdateExpenseAction.php',
        'app/Http/Controllers/Inventory/StockTransfersController.php',
        'app/Models/Storage.php',
    ];

    $missing = array_values(array_filter(
        $mustLock,
        fn ($relative) => ! str_contains(file_get_contents(base_path($relative)), 'ChangeLog::lockTenant')
    ));

    expect($missing)->toBe([]);
});
