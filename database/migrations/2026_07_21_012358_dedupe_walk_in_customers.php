<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Checkout used to match the walk-in customer by its display name, so a
     * translated tenant ended up with two system walk-ins: the seeded (localized)
     * one and a literal "Walk-in Customer" created at checkout. Collapse them:
     * keep the oldest system customer per tenant, move invoices onto it, and
     * soft-delete the duplicates. Idempotent — tenants with a single walk-in are
     * left untouched.
     */
    public function up(): void
    {
        $original = app()->bound('currentTenant') ? app('currentTenant') : null;

        try {
            Tenant::query()->each(function (Tenant $tenant) {
                app()->instance('currentTenant', $tenant);

                $walkIns = Customer::where('is_system', true)->orderBy('id')->get();

                if ($walkIns->count() < 2) {
                    return;
                }

                $canonical = $walkIns->shift();

                foreach ($walkIns as $duplicate) {
                    Invoice::where('invocable_type', Customer::class)
                        ->where('invocable_id', $duplicate->id)
                        ->update(['invocable_id' => $canonical->id]);

                    $duplicate->delete();
                }
            });
        } finally {
            if ($original) {
                app()->instance('currentTenant', $original);
            } else {
                app()->forgetInstance('currentTenant');
            }
        }
    }

    public function down(): void
    {
        // Merged customers cannot be reconstructed; no-op.
    }
};
