<?php

namespace App\Services;

use App\Exports\CustomerExport;
use App\Exports\ExpenseExport;
use App\Exports\ProductExport;
use App\Exports\Reports\CustomerAgingExport;
use App\Exports\Reports\ExpenseSummaryExport;
use App\Exports\Reports\InventoryValuationExport;
use App\Exports\Reports\PosSessionExport;
use App\Exports\Reports\ProfitAndLossExport;
use App\Exports\Reports\PurchaseReportExport;
use App\Exports\Reports\SalesReportExport;
use App\Exports\Reports\SupplierAgingExport;
use App\Exports\Reports\TreasuryReconciliationExport;
use App\Exports\SupplierExport;

class ExportRegistry
{
    /** @return array<string, class-string> */
    public static function exports(): array
    {
        return [
            'customers' => CustomerExport::class,
            'suppliers' => SupplierExport::class,
            'products' => ProductExport::class,
            'expenses' => ExpenseExport::class,
            'report-sales' => SalesReportExport::class,
            'report-purchases' => PurchaseReportExport::class,
            'report-pos-sessions' => PosSessionExport::class,
            'report-inventory-valuation' => InventoryValuationExport::class,
            'report-customer-aging' => CustomerAgingExport::class,
            'report-supplier-aging' => SupplierAgingExport::class,
            'report-treasury' => TreasuryReconciliationExport::class,
            'report-expenses' => ExpenseSummaryExport::class,
            'report-pnl' => ProfitAndLossExport::class,
        ];
    }

    public static function resolve(string $key): ?string
    {
        return self::exports()[$key] ?? null;
    }

    public static function isValid(string $key): bool
    {
        return array_key_exists($key, self::exports());
    }
}
