<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_access_payments_index()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('payments.index'));

        $response->assertStatus(200);
    }

    public function test_can_search_payments_by_invoice_serial_number()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['name' => 'Searchable Customer']);

        $invoice = Invoice::create([
            'serial_number' => 'TEST-SERIAL-999',
            'invocable_id' => $customer->id,
            'invocable_type' => Customer::class,
            'total' => 100,
            'status' => 'paid_status',
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'payment_method' => 'cash',
            'paid_at' => now(),
            'created_by' => $user->id,
        ]);

        // Search for TEST-SERIAL-999
        $response = $this->actingAs($user)->get(route('payments.index', ['search' => 'TEST-SERIAL-999']));

        $response->assertStatus(200);
        $payments = $response->viewData('page')['props']['payments']['data'];
        $this->assertCount(1, $payments);
        $this->assertEquals($payment->id, $payments[0]['id']);
    }

    public function test_can_search_payments_by_customer_name()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['name' => 'Specific Customer Name']);

        $invoice = Invoice::create([
            'invocable_id' => $customer->id,
            'invocable_type' => Customer::class,
            'total' => 100,
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'payment_method' => 'cash',
            'paid_at' => now(),
            'created_by' => $user->id,
        ]);

        // Search for customer name
        $response = $this->actingAs($user)->get(route('payments.index', ['search' => 'Specific Customer']));

        $response->assertStatus(200);
        $payments = $response->viewData('page')['props']['payments']['data'];
        $this->assertCount(1, $payments);
        $this->assertEquals($payment->id, $payments[0]['id']);
    }
}
