<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;
use BaconQrCode\Writer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Browsershot\Browsershot;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('it can show an invoice', function () {
    $customer = Customer::factory()->create();
    $invoice = Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
    ]);

    $response = $this->get(route('invoices.show', $invoice));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Invoice')
        ->has('invoice')
        ->has('storages')
    );
});

test('it can print an invoice', function () {
    $customer = Customer::factory()->create();
    $invoice = Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
    ]);

    Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'delivered' => true,
    ]);

    Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'delivered' => false,
    ]);

    // Mock Browsershot
    $browsershotMock = Mockery::mock('overload:'.Browsershot::class);
    $browsershotMock->shouldReceive('html')->once()->andReturnSelf();
    $browsershotMock->shouldReceive('noSandbox')->once()->andReturnSelf();
    $browsershotMock->shouldReceive('disableJavascript')->once()->andReturnSelf();
    $browsershotMock->shouldReceive('format')->with('A4')->once()->andReturnSelf();
    $browsershotMock->shouldReceive('pdf')->once()->andReturn('PDF_CONTENT');

    // Mock BaconQrCode Writer
    $writerMock = Mockery::mock('overload:'.Writer::class);
    $writerMock->shouldReceive('writeString')->once()->andReturn('QR_CODE_SVG');

    $response = $this->get(route('invoices.print', $invoice));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toBe('PDF_CONTENT');
});
