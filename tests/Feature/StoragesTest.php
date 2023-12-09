<?php

use App\Models\Storage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('Auth users only can see storages page', function () {
    $this->get(route('storages.index'))
        ->assertRedirect();
    $user = User::factory()->create();
    $this->be($user)
        ->get(route('storages.index'))
        ->assertOk();
});

test('Auth user can see storages', function () {
    $storage = Storage::factory()->create();
    $this->signIn(User::factory()->create())
        ->get(route('storages.show', $storage))
        ->assertInertia(fn (Assert $page) => $page->component('Storages/Show')
        );
});

test('Auth users can create a new storages', function () {
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
    $storage = Storage::factory()->create();

    $this->signIn()
        ->delete(route('storages.destroy', $storage));
    $this->assertNotSoftDeleted(Storage::class, ['id' => $storage->id]);

    $this->signIn(User::factory()->admin()->create())
        ->delete(route('storages.destroy', $storage));
    $this->assertSoftDeleted(Storage::class, ['id' => $storage->id]);

});
