<?php

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Index ──────────────────────────────────────────────────────────────────────

it('lists quotes', function () {
    Quote::factory()->count(3)->create();

    actingAsTenantUser()
        ->get(route('quotes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Quotes/Index')->has('quotes'));
});

it('filters quotes by status', function () {
    Quote::factory()->active()->create();
    Quote::factory()->converted()->create();

    actingAsTenantUser()
        ->get(route('quotes.index', ['status' => 'active']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->has('quotes.data', 1)
        );
});

// ── Create ─────────────────────────────────────────────────────────────────────

it('renders the create quote page', function () {
    actingAsTenantUser()
        ->get(route('quotes.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Quotes/Create'));
});

it('stores a quote with items and snapshots unit price', function () {
    $product = Product::factory()->create();

    $payload = [
        'customer_id' => null,
        'discount' => 10,
        'notes' => 'Test quote',
        'items' => [
            [
                'product_id' => $product->id,
                'unit_id' => null,
                'quantity' => 2,
                'unit_price' => 50, // snapshot — not live selling_price
            ],
        ],
    ];

    actingAsTenantUser()
        ->post(route('quotes.store'), $payload)
        ->assertRedirect(route('quotes.index'));

    $quote = Quote::latest()->first();

    expect($quote)->not->toBeNull();
    expect($quote->number)->toStartWith('Q-');
    expect($quote->discount)->toEqual('10.00');
    expect($quote->items)->toHaveCount(1);
    expect($quote->items->first()->unit_price)->toEqual('50.00'); // snapshot preserved
});

it('requires at least one item', function () {
    actingAsTenantUser()
        ->post(route('quotes.store'), [
            'items' => [],
        ])
        ->assertSessionHasErrors('items');
});

it('rejects zero quantity', function () {
    $product = Product::factory()->create();

    actingAsTenantUser()
        ->post(route('quotes.store'), [
            'items' => [
                ['product_id' => $product->id, 'unit_id' => null, 'quantity' => 0, 'unit_price' => 10],
            ],
        ])
        ->assertSessionHasErrors('items.0.quantity');
});

// ── Edit ───────────────────────────────────────────────────────────────────────

it('renders the edit quote page', function () {
    $quote = Quote::factory()->has(QuoteItem::factory()->count(2), 'items')->create();

    actingAsTenantUser()
        ->get(route('quotes.edit', $quote))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Quotes/Edit')->has('quote'));
});

it('updates a quote and re-syncs items', function () {
    $quote = Quote::factory()->has(QuoteItem::factory()->count(2), 'items')->create();
    $newProduct = Product::factory()->create();

    actingAsTenantUser()
        ->put(route('quotes.update', $quote), [
            'customer_id' => null,
            'discount' => 5,
            'notes' => 'Updated',
            'items' => [
                ['product_id' => $newProduct->id, 'unit_id' => null, 'quantity' => 3, 'unit_price' => 20],
            ],
        ])
        ->assertRedirect(route('quotes.index'));

    $quote->refresh();
    expect($quote->items)->toHaveCount(1);
    expect($quote->items->first()->quantity)->toBe(3);
    expect((float) $quote->discount)->toBe(5.0);
});

// ── Delete ─────────────────────────────────────────────────────────────────────

it('soft-deletes a quote', function () {
    $quote = Quote::factory()->create();

    actingAsTenantUser()
        ->delete(route('quotes.destroy', $quote))
        ->assertRedirect(route('quotes.index'));

    expect(Quote::withTrashed()->find($quote->id))->not->toBeNull();
    expect(Quote::find($quote->id))->toBeNull();
});

// ── Convert ────────────────────────────────────────────────────────────────────

it('redirects to sales create with from_quote param when converting', function () {
    $quote = Quote::factory()->create();

    actingAsTenantUser()
        ->get(route('quotes.convert', $quote))
        ->assertRedirect(route('sales.create', ['from_quote' => $quote->id]));
});

it('marks quote as converted when invoice is stored from quote', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $unit = Unit::factory()->create(['product_id' => $product->id]);
    $quote = Quote::factory()->create(['customer_id' => $customer->id]);

    actingAsTenantUser()
        ->post(route('sales.store'), [
            'from_quote_id' => $quote->id,
            'total' => 100,
            'invocable' => ['id' => $customer->id, 'name' => $customer->name, 'type' => 'App\Models\Customer'],
            'products' => [
                ['product' => $product->id, 'quantity' => 1, 'unit' => $unit->id, 'price' => 100],
            ],
            'payment_method' => 'cash',
        ])
        ->assertRedirect(route('sales.index'));

    $quote->refresh();
    expect($quote->status)->toBe(QuoteStatus::Converted);
    expect($quote->converted_to_invoice_id)->not->toBeNull();
});

// ── Print ──────────────────────────────────────────────────────────────────────

it('renders the print quote page', function () {
    $quote = Quote::factory()->has(QuoteItem::factory()->count(1), 'items')->create();

    actingAsTenantUser()
        ->get(route('quotes.print', $quote))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Quotes/Print'));
});

// ── Expiry ─────────────────────────────────────────────────────────────────────

it('flags an expired quote via is_expired attribute', function () {
    $quote = Quote::factory()->create([
        'expires_at' => now()->subDay(),
    ]);

    expect($quote->is_expired)->toBeTrue();
});

it('does not flag a future quote as expired', function () {
    $quote = Quote::factory()->create([
        'expires_at' => now()->addDay(),
    ]);

    expect($quote->is_expired)->toBeFalse();
});

it('does not flag a quote with no expiry as expired', function () {
    $quote = Quote::factory()->create(['expires_at' => null]);

    expect($quote->is_expired)->toBeFalse();
});

// ── Computed ───────────────────────────────────────────────────────────────────

it('computes subtotal and total correctly', function () {
    $quote = Quote::factory()->create(['discount' => 15]);
    QuoteItem::factory()->create(['quote_id' => $quote->id, 'quantity' => 2, 'unit_price' => 100]);
    QuoteItem::factory()->create(['quote_id' => $quote->id, 'quantity' => 1, 'unit_price' => 50]);

    $quote->load('items');

    expect($quote->subtotal)->toBe(250.0);
    expect($quote->total)->toBe(235.0);
});

// ── Observer ───────────────────────────────────────────────────────────────────

it('auto-generates a quote number after creation', function () {
    $quote = Quote::factory()->create();

    expect($quote->number)->toMatch('/^Q-\d{2}-\d+$/');
});
