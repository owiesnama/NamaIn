<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use Inertia\Response;

class QuotePrintController extends Controller
{
    public function show(Quote $quote): Response
    {
        $quote->load(['customer', 'items.product', 'items.unit']);

        return inertia('Quotes/Print', [
            'quote' => $quote,
        ]);
    }
}
