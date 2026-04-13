<?php

use App\Models\Bank;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->bank = Bank::create(['name' => 'Test Bank']);
});

test('can register a new cheque via cheques.store', function () {
    $customer = Customer::factory()->create();

    $response = $this->post(route('cheques.store'), [
        'payee_id' => $customer->id,
        'payee_type' => get_class($customer),
        'type' => 1, // Credit
        'amount' => 1250.50,
        'bank_id' => $this->bank->id,
        'reference_number' => 'CHQ-999',
        'due' => now()->addDays(15)->toDateString(),
    ]);

    $response->assertRedirect(route('cheques.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('cheques', [
        'chequeable_id' => $customer->id,
        'chequeable_type' => get_class($customer),
        'amount' => 1250.50,
        'bank_id' => $this->bank->id,
        'bank' => 'Test Bank',
        'reference_number' => 'CHQ-999',
    ]);
});

test('cheque registration validation fails with missing fields', function () {
    $response = $this->post(route('cheques.store'), []);

    $response->assertSessionHasErrors(['payee_id', 'payee_type', 'type', 'amount', 'bank_id', 'reference_number', 'due']);
});
