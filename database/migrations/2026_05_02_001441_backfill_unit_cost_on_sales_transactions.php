<?php

use App\Models\Customer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $customerType = (new Customer)->getMorphClass();

        // Set unit_cost to weighted average purchase cost per product
        // Falls back to products.cost when no purchase history exists
        DB::statement('
            UPDATE transactions
            SET unit_cost = COALESCE(
                (
                    SELECT CASE WHEN SUM(t2.base_quantity) > 0
                        THEN SUM(t2.base_quantity * t2.unit_cost) / SUM(t2.base_quantity)
                        ELSE NULL END
                    FROM transactions t2
                    INNER JOIN invoices i2 ON t2.invoice_id = i2.id
                    WHERE t2.product_id = transactions.product_id
                      AND t2.delivered = true
                      AND i2.invocable_type != ?
                      AND t2.unit_cost IS NOT NULL
                ),
                (SELECT p.cost FROM products p WHERE p.id = transactions.product_id),
                0
            )
            WHERE unit_cost IS NULL
              AND EXISTS (
                  SELECT 1 FROM invoices i
                  WHERE i.id = transactions.invoice_id
                    AND i.invocable_type = ?
              )
        ', [$customerType, $customerType]);
    }
};
