<?php

namespace App\Enums;

use App\Models\Adjustment;
use App\Models\Bank;
use App\Models\Category;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\CustomerAdvance;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\RecurringExpense;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionReceipt;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use App\Models\TreasuryTransfer;
use App\Models\Unit;

enum TenantDataGroup: string
{
    case Commercial = 'commercial';
    case Inventory = 'inventory';
    case Financial = 'financial';

    /**
     * Groups that must also be cleared when this group is selected.
     *
     * @return array<self>
     */
    public function dependencies(): array
    {
        return match ($this) {
            self::Commercial => [self::Inventory, self::Financial],
            self::Inventory, self::Financial => [],
        };
    }

    /**
     * Models belonging to this group. Order matters — delete children before parents.
     *
     * @return array<class-string>
     */
    public function models(): array
    {
        return match ($this) {
            self::Commercial => [
                TransactionReceipt::class,
                Transaction::class,
                Payment::class,
                CustomerAdvance::class,
                Cheque::class,
                Invoice::class,
                Customer::class,
                Supplier::class,
            ],
            self::Inventory => [
                StockTransferLine::class,
                StockTransfer::class,
                StockMovement::class,
                Stock::class,
                Adjustment::class,
                PosSession::class,
                Unit::class,
                Product::class,
            ],
            self::Financial => [
                TreasuryMovement::class,
                TreasuryTransfer::class,
                TreasuryAccount::class,
                RecurringExpense::class,
                Expense::class,
                Bank::class,
                Category::class,
            ],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Commercial => 'Commercial Data',
            self::Inventory => 'Inventory',
            self::Financial => 'Financial',
        };
    }

    /**
     * Resolve a set of groups with all their dependencies included.
     *
     * @param  array<self>  $groups
     * @return array<self>
     */
    public static function resolveWithDependencies(array $groups): array
    {
        $resolved = [];

        foreach ($groups as $group) {
            $resolved[] = $group;
            foreach ($group->dependencies() as $dep) {
                $resolved[] = $dep;
            }
        }

        return array_values(array_unique($resolved, SORT_REGULAR));
    }
}
