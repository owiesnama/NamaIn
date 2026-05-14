<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Inertia\Response;

class InvoiceReceiptController extends Controller
{
    public function show(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load(['invocable', 'transactions.product']);

        return inertia('Invoices/Receipt', [
            'invoice' => $invoice,
            'logo' => preference('logo', '/images/logo.svg'),
            'headline' => preference('invoicesHeadline'),
            'currency' => $invoice->currency ?: preference('currency', 'SDG'),
        ]);
    }
}
