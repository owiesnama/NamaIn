<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Supplier;
use App\Services\StatementService;
use BaconQrCode\Writer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Browsershot\Browsershot;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it generates a pdf statement', function () {
    // Disable observers for the test
    Invoice::unsetEventDispatcher();

    $supplier = Supplier::factory()->create(['name' => 'Test Supplier']);

    // Create an invoice for the supplier
    $invoice = Invoice::factory()->create([
        'invocable_id' => $supplier->id,
        'invocable_type' => Supplier::class,
        'total' => 1000,
        'discount' => 0,
        'paid_amount' => 200,
        'created_at' => now()->subDays(5),
    ]);

    // Create a payment for that invoice
    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 200,
        'paid_at' => now()->subDays(2),
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

    $service = new StatementService;
    $startDate = now()->subMonth()->toDateTimeString();
    $endDate = now()->toDateTimeString();

    $result = $service->generatePdf($supplier, $startDate, $endDate);

    expect($result)->toBe('PDF_CONTENT');
});
