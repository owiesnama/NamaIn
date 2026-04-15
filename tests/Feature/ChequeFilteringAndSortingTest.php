<?php

use App\Enums\ChequeStatus;
use App\Models\Bank;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->bank = Bank::create(['name' => 'Test Bank']);
});

test('can filter cheques by search term', function () {
    $customer1 = Customer::factory()->create(['name' => 'John Doe']);
    $customer2 = Customer::factory()->create(['name' => 'Jane Smith']);

    Cheque::factory()->create([
        'chequeable_id' => $customer1->id,
        'chequeable_type' => get_class($customer1),
        'reference_number' => 'CHQ-111',
    ]);
    Cheque::factory()->create([
        'chequeable_id' => $customer2->id,
        'chequeable_type' => get_class($customer2),
        'reference_number' => 'CHQ-222',
    ]);

    $this->get(route('cheques.index', ['search' => 'John']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('initialCheques.data', 1)
            ->where('initialCheques.data.0.reference_number', 'CHQ-111')
        );

    $this->get(route('cheques.index', ['search' => 'CHQ-222']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('initialCheques.data', 1)
            ->where('initialCheques.data.0.reference_number', 'CHQ-222')
        );
});

test('can filter cheques by status', function () {
    Cheque::factory()->create(['status' => ChequeStatus::Drafted]);
    Cheque::factory()->create(['status' => ChequeStatus::Issued]);
    Cheque::factory()->create(['status' => ChequeStatus::Cleared]);

    $this->get(route('cheques.index', ['status' => ChequeStatus::Drafted->value]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('initialCheques.data', 1)
            ->where('initialCheques.data.0.status', ChequeStatus::Drafted->value)
        );
});

test('can filter cheques by multiple statuses simultaneously', function () {
    Cheque::factory()->create(['status' => ChequeStatus::Drafted]);
    Cheque::factory()->create(['status' => ChequeStatus::Issued]);
    Cheque::factory()->create(['status' => ChequeStatus::Cleared]);

    $this->get(route('cheques.index', [
        'status' => [ChequeStatus::Drafted->value, ChequeStatus::Issued->value],
    ]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('initialCheques.data', 2)
            ->where('initialCheques.data.0.status', fn ($status) => in_array($status, [ChequeStatus::Drafted->value, ChequeStatus::Issued->value]))
            ->where('initialCheques.data.1.status', fn ($status) => in_array($status, [ChequeStatus::Drafted->value, ChequeStatus::Issued->value]))
        );
});

test('can filter cheques by type', function () {
    Cheque::factory()->create(['type' => 1]); // Receivable
    Cheque::factory()->create(['type' => 0]); // Payable

    $this->get(route('cheques.index', ['type' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('initialCheques.data', 1)
            ->where('initialCheques.data.0.type', 1)
        );

    $this->get(route('cheques.index', ['type' => 0]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('initialCheques.data', 1)
            ->where('initialCheques.data.0.type', 0)
        );
});

test('can filter cheques by due date', function () {
    Cheque::factory()->create(['due' => now()->subDays(5)]);
    Cheque::factory()->create(['due' => now()->addDays(5)]);

    $this->get(route('cheques.index', ['due' => now()->toDateString()]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('initialCheques.data', 1)
        );
});

test('can sort cheques by different fields', function () {
    Cheque::factory()->create(['amount' => 100, 'due' => now()->addDays(10), 'reference_number' => 'B']);
    Cheque::factory()->create(['amount' => 200, 'due' => now()->addDays(5), 'reference_number' => 'A']);

    // Sort by amount asc
    $this->get(route('cheques.index', ['sort_by' => 'amount', 'sort_order' => 'asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('initialCheques.data.0.amount', 100)
            ->where('initialCheques.data.1.amount', 200)
        );

    // Sort by amount desc
    $this->get(route('cheques.index', ['sort_by' => 'amount', 'sort_order' => 'desc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('initialCheques.data.0.amount', 200)
            ->where('initialCheques.data.1.amount', 100)
        );

    // Sort by due asc (default)
    $this->get(route('cheques.index', ['sort_by' => 'due', 'sort_order' => 'asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('initialCheques.data.0.amount', 200)
            ->where('initialCheques.data.1.amount', 100)
        );

    // Sort by reference_number asc
    $this->get(route('cheques.index', ['sort_by' => 'reference_number', 'sort_order' => 'asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('initialCheques.data.0.reference_number', 'A')
            ->where('initialCheques.data.1.reference_number', 'B')
        );
});
