<?php

use App\Models\Storage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('Auth users only can see storages page', function () {
    /** @var TestCase $this */
    $this->get(route('storages.index'))
        ->assertRedirect();
    $user = User::factory()->create();
    $this->be($user)
        ->get(route('storages.index'))
        ->assertOk();
});

test('Auth users can create a new storages', function () {
    /** @var TestCase $this */
    $storageAttributes = [
        'name' => 'Fake Storage',
        'address' => 'wad madni',
    ];
    $this->post(route('storages.store'), $storageAttributes)->assertRedirect();
    $this->assertDatabaseMissing('storages', [
        'name' => 'Fake Storage',
        'address' => 'wad madni',
    ]);
    $user = User::factory()->create();
    $this->be($user)
        ->post(route('storages.store'), $storageAttributes)
        ->assertRedirect();
    $this->assertDatabaseHas('storages', [
        'name' => 'Fake Storage',
        'address' => 'wad madni',
    ]);
});

test('Auth users can update a storage', function () {
    /** @var TestCase $this */
    $storage = Storage::factory()->create();
    $storageAttributes = [
        'name' => 'Updated Storage',
        'address' => 'New Address',
    ];

    $this->put(route('storages.update', $storage), $storageAttributes)
        ->assertRedirect();

    $user = User::factory()->create();
    $this->be($user)
        ->put(route('storages.update', $storage), $storageAttributes)
        ->assertRedirect();

    $this->assertDatabaseHas(Storage::class, $storageAttributes);
});

test('Auth user can delete a storage', function () {
    /** @var TestCase this */
});
