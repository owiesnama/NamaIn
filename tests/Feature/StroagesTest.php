<?php

use App\Models\Storage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('only_auth_users_can_see_stroages_page', function () {
    /** @var TestCase $this */
    $this->get('/storages')
        ->assertRedirect();
    $user = User::factory()->create();
    $this->be($user)
        ->get('/storages')
        ->assertOk();
});

test('auth_users_can_create_a_new_stroages', function () {
    /** @var TestCase $this */
    $storageAttributes = [
        'name' => 'Fake Storage',
        'address' => 'wad madni',
    ];
    $this->post('/storages', $storageAttributes)->assertRedirect();
    $this->assertDatabaseMissing('storages',[
        'name' => 'Fake Storage',
        'address' => 'wad madni',
    ]);
    $user = User::factory()->create();
    $this->be($user)
        ->post('/storages', $storageAttributes)
        ->assertRedirect();
        $this->assertDatabaseHas('storages',[
            'name' => 'Fake Storage',
            'address' => 'wad madni',
        ]);
});


test('auth_users_can_update_a_storage', function () {
    /** @var TestCase $this */
    $storage = Storage::factory()->create();
    $storageAttributes = [
        'name' => 'Updated Storage',
        'address' => 'New Address'
    ];
    $this->put("/storages/{$storage->id}", $storageAttributes)->assertRedirect();
    $user = User::factory()->create();
    $this->be($user)
        ->put("/storages/{$storage->id}", $storageAttributes)
        ->assertRedirect()
        ->assertSessionHas('flash', [
            'title' => 'Storage updated 🎉',
            'message' => 'Storage updated successfully'
        ]);
});

test("auth_user_can_delete_a_stroge", function () {
/** @var TestCase this */

});