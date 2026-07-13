<?php

namespace App\Reports;

use Illuminate\Contracts\Container\Container;

class ReportRegistry
{
    /**
     * Slug-to-report map; the single source of truth for available reports.
     *
     * @var array<string, class-string<Report>>
     */
    private const REPORTS = [
        'sales' => SalesReport::class,
        'purchases' => PurchaseReport::class,
        'pos-sessions' => PosSessionReport::class,
        'inventory-valuation' => InventoryValuationReport::class,
        'negative-stock' => NegativeStockReport::class,
        'customer-aging' => CustomerAgingReport::class,
        'supplier-aging' => SupplierAgingReport::class,
        'treasury-reconciliation' => TreasuryReconciliationReport::class,
        'expense-summary' => ExpenseSummaryReport::class,
        'profit-and-loss' => ProfitAndLossReport::class,
    ];

    /**
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return array_keys(self::REPORTS);
    }

    public function __construct(private Container $container) {}

    public function resolve(string $slug): ?Report
    {
        $class = self::REPORTS[$slug] ?? null;

        if ($class === null) {
            return null;
        }

        return $this->container->make($class);
    }
}
