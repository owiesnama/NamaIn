<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('logout destroys all sessions for the user', function () {
    $user = User::factory()->create();

    // Simulate two sessions belonging to the user
    DB::table('sessions')->insert([
        ['id' => 'session-one', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Test', 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'session-two', 'user_id' => $user->id, 'ip_address' => '127.0.0.2', 'user_agent' => 'Test', 'payload' => '', 'last_activity' => now()->timestamp],
    ]);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(2);

    $this->actingAs($user)->post(route('logout'));

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

test('logout does not affect sessions of other users', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    DB::table('sessions')->insert([
        ['id' => 'user-session', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Test', 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'other-session', 'user_id' => $otherUser->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Test', 'payload' => '', 'last_activity' => now()->timestamp],
    ]);

    $this->actingAs($user)->post(route('logout'));

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
    expect(DB::table('sessions')->where('user_id', $otherUser->id)->count())->toBe(1);
});
