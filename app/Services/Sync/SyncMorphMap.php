<?php

namespace App\Services\Sync;

use App\Models\Adjustment;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\CustomerAdvance;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionReceipt;
use App\Models\TreasuryTransfer;
use Illuminate\Support\Str;

/**
 * Maps Eloquent morph classes to short wire tokens (Design 02 §7). The DB
 * stores FQCNs (no app-wide morph map); the wire must never leak PHP class
 * names, so morph columns are translated at the boundary in both directions.
 */
final class SyncMorphMap
{
    /** @var array<string, class-string> wire token => model class */
    private const MAP = [
        'adjustment' => Adjustment::class,
        'cheque' => Cheque::class,
        'customer' => Customer::class,
        'customer_advance' => CustomerAdvance::class,
        'expense' => Expense::class,
        'invoice' => Invoice::class,
        'payment' => Payment::class,
        'pos_session' => PosSession::class,
        'product' => Product::class,
        'stock_transfer' => StockTransfer::class,
        'supplier' => Supplier::class,
        'transaction' => Transaction::class,
        'transaction_receipt' => TransactionReceipt::class,
        'treasury_transfer' => TreasuryTransfer::class,
    ];

    public static function token(?string $modelClass): ?string
    {
        if ($modelClass === null) {
            return null;
        }

        $token = array_search($modelClass, self::MAP, true);

        return $token !== false ? $token : Str::snake(class_basename($modelClass));
    }

    /** @return class-string|null */
    public static function modelClass(?string $token): ?string
    {
        if ($token === null) {
            return null;
        }

        return self::MAP[$token] ?? null;
    }
}
