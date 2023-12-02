<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        $invoice->serial_number = $this->generateSerialNumber($invoice);
        $invoice->save();
    }

    public function generateSerialNumber($invoice)
    {
        $lookup = [
            Customer::class => 'SA',
            Supplier::class => 'SU',
        ];
        $prefix = 'INV';
        $typePerfix = $lookup[$invoice->invocable_type];
        $date = now()->format('y');
        $serialNumber = "{$prefix}-{$typePerfix}-{$date}-{$invoice->id}";

        return $serialNumber;
    }
}
