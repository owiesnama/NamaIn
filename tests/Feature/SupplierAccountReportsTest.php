<?php

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('supplier account page is accessible', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('suppliers.account', $supplier));

    $response->assertStatus(200);
});

test('supplier statement page is accessible', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('suppliers.statement', $supplier));

    $response->assertStatus(200);
});
