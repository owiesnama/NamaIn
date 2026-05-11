<?php

namespace App\Http\Controllers\Sales;

use App\Actions\StoreQuoteAction;
use App\Actions\UpdateQuoteAction;
use App\Filters\QuoteFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuoteRequest;
use App\Models\Quote;

class QuotesController extends Controller
{
    public function index(QuoteFilter $filter)
    {
        return inertia('Quotes/Index', [
            'quotes' => Quote::filter($filter)
                ->with(['customer', 'items'])
                ->latest()
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create()
    {
        return inertia('Quotes/Create');
    }

    public function store(StoreQuoteRequest $request, StoreQuoteAction $action)
    {
        $action->handle($request->validated());

        return redirect()->route('quotes.index')->with('success', __('Quote created successfully'));
    }

    public function edit(Quote $quote)
    {
        $quote->load(['customer', 'items.product', 'items.unit']);

        return inertia('Quotes/Edit', [
            'quote' => $quote,
        ]);
    }

    public function update(StoreQuoteRequest $request, UpdateQuoteAction $action, Quote $quote)
    {
        $action->handle($quote, $request->validated());

        return redirect()->route('quotes.index')->with('success', __('Quote updated successfully'));
    }

    public function destroy(Quote $quote)
    {
        $quote->delete();

        return redirect()->route('quotes.index')->with('success', __('Quote deleted successfully'));
    }
}
