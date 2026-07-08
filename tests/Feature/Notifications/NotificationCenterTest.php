<?php

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

function makeNotification(User $user, array $overrides = []): DatabaseNotification
{
    return $user->notifications()->create(array_merge([
        'id' => (string) Str::uuid(),
        'type' => 'announcement',
        'data' => ['title' => 'Hello', 'body' => 'World', 'url' => '/notifications'],
        'read_at' => null,
    ], $overrides));
}

it('lists only the authenticated user notifications', function () {
    actingAsTenantUser();

    makeNotification(auth()->user());
    makeNotification(User::factory()->create());

    $this->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Notifications/Index')
            ->has('notifications.data', 1));
});

it('returns the latest items and unread count from the feed', function () {
    actingAsTenantUser();

    foreach (range(1, 12) as $i) {
        makeNotification(auth()->user());
    }

    $this->getJson(route('notifications.feed'))
        ->assertOk()
        ->assertJsonCount(10, 'items')
        ->assertJsonPath('unread_count', 12);
});

it('marks a notification as read', function () {
    actingAsTenantUser();

    $notification = makeNotification(auth()->user());

    $this->put(route('notifications.read', $notification->id))->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('returns 404 when marking another user notification', function () {
    actingAsTenantUser();

    $foreign = makeNotification(User::factory()->create());

    $this->put(route('notifications.read', $foreign->id))->assertNotFound();

    expect($foreign->fresh()->read_at)->toBeNull();
});

it('marks all notifications as read', function () {
    actingAsTenantUser();

    makeNotification(auth()->user());
    makeNotification(auth()->user());

    $this->put(route('notifications.read-all'))->assertRedirect();

    expect(auth()->user()->unreadNotifications()->count())->toBe(0);
});

it('shares the unread count with every Inertia page', function () {
    actingAsTenantUser();

    makeNotification(auth()->user());

    $this->get(route('notifications.index'))
        ->assertInertia(fn ($page) => $page->where('unreadNotifications', 1));
});
