<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Quote;

class QuoteConvertController extends Controller
{
    public function show(Quote $quote)
    {
        return to_route('sales.create', ['from_quote' => $quote->id]);
    }
}
