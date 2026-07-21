<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;

/**
 * Legacy PK-based invoice numbering. Tenants with the offline-sync entitlement
 * receive a register-scoped serial in the `creating` hook instead
 * ({@see Invoice::assignSerialNumber}), so this observer only fires for
 * tenants where that flag is off (the serial is still empty after insert).
 */
class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        if (empty($invoice->serial_number)) {
            $invoice->serial_number = $this->generateSerialNumber($invoice);
            $invoice->save();
        }
    }

    public function generateSerialNumber($invoice)
    {
        $lookup = [
            Customer::class => 'SA',
            Supplier::class => 'SU',
        ];

        $prefix = $invoice->is_inverse ? 'RET' : 'INV';
        $typePrefix = $lookup[$invoice->invocable_type];
        $date = now()->format('y');
        $serialNumber = "{$prefix}-{$typePrefix}-{$date}-{$invoice->id}";

        return $serialNumber;
    }
}
