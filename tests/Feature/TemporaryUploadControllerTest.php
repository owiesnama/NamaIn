<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('guest cannot upload temporary file', function () {
    $response = $this->postJson(route('uploads.tmp.store'), [
        'receipt' => UploadedFile::fake()->create('receipt.jpg', 100),
    ]);

    $response->assertStatus(401);
});

test('user can upload temporary file', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('uploads.tmp.store'), [
            'receipt' => UploadedFile::fake()->create('receipt.jpg', 100),
        ]);

    $response->assertStatus(200);
    $filename = $response->getContent();
    expect($filename)->toMatch('/^[0-9a-f-]+\.jpg$/');

    Storage::disk('local')->assertExists('tmp/'.$filename);
});

test('user can delete temporary file', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    // First upload
    $uploadResponse = $this->actingAs($user)
        ->postJson(route('uploads.tmp.store'), [
            'receipt' => UploadedFile::fake()->create('receipt.jpg', 100),
        ]);

    $filename = $uploadResponse->getContent();

    // Then delete
    $deleteResponse = $this->actingAs($user)
        ->call('DELETE', route('uploads.tmp.destroy'), [], [], [], ['CONTENT_TYPE' => 'text/plain'], $filename);

    $deleteResponse->assertStatus(204);
    Storage::disk('local')->assertMissing('tmp/'.$filename);
});

test('validation fails for invalid file type', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('uploads.tmp.store'), [
            'receipt' => UploadedFile::fake()->create('document.txt', 100),
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['receipt']);
});

test('validation fails for file too large', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('uploads.tmp.store'), [
            'receipt' => UploadedFile::fake()->create('large.jpg', 6000), // > 5MB
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['receipt']);
});
