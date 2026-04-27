<?php

namespace App\Actions;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StorePurchaseAction
{
    public function __construct(private StoreInvoiceAction $storeInvoice) {}

    public function handle(Collection $data, ?Request $request = null): Invoice
    {
        return $this->storeInvoice->handle($data, $request);
    }
}
