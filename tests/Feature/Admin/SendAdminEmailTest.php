<?php

use App\Models\User;
use App\Notifications\AdminCustomEmailNotification;
use Illuminate\Support\Facades\Notification;

it('renders the messaging page for a super admin', function () {
    User::factory()->count(3)->create();

    actingAsSuperAdmin();

    $this->get(route('admin.messages.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Messages/Index'));
});

it('filters users to unverified only', function () {
    $verified = User::factory()->create();
    $verified->markEmailAsVerified();
    User::factory()->unverified()->create();

    actingAsSuperAdmin(); // acting admin is verified

    $this->get(route('admin.messages.index', ['verified' => 'unverified']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('users.data', 1));
});

it('searches users by name or email', function () {
    User::factory()->create(['name' => 'Alice Owner', 'email' => 'alice@example.com']);
    User::factory()->create(['name' => 'Bob Other', 'email' => 'bob@example.com']);

    actingAsSuperAdmin();

    $this->get(route('admin.messages.index', ['search' => 'alice']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('users.data', 1));
});

it('queues a custom email to the selected users only', function () {
    Notification::fake();

    $selected = User::factory()->count(2)->create();
    $excluded = User::factory()->create();

    actingAsSuperAdmin();

    $this->post(route('admin.messages.store'), [
        'user_ids' => $selected->pluck('id')->all(),
        'subject' => 'Please verify your account',
        'body' => "Hello,\n\nWe noticed you couldn't verify your email.",
    ])->assertRedirect()->assertSessionHas('success');

    Notification::assertSentTo($selected, AdminCustomEmailNotification::class);
    Notification::assertNotSentTo($excluded, AdminCustomEmailNotification::class);
});

it('passes the sender name and reply-to through to the notification', function () {
    Notification::fake();

    $recipient = User::factory()->create();

    actingAsSuperAdmin();

    $this->post(route('admin.messages.store'), [
        'user_ids' => [$recipient->id],
        'from_name' => 'NamaIn Team',
        'reply_to' => 'support@namain.test',
        'subject' => 'A promo',
        'body' => 'Check out our new features.',
    ])->assertRedirect();

    Notification::assertSentTo($recipient, AdminCustomEmailNotification::class, function ($notification) {
        return $notification->fromName === 'NamaIn Team'
            && $notification->replyTo === 'support@namain.test'
            && $notification->subject === 'A promo';
    });
});

it('validates required fields and reply-to format', function () {
    actingAsSuperAdmin();

    $this->post(route('admin.messages.store'), [
        'user_ids' => [],
        'reply_to' => 'not-an-email',
        'subject' => '',
        'body' => '',
    ])->assertSessionHasErrors(['user_ids', 'reply_to', 'subject', 'body']);
});

it('forbids non-admins from viewing or sending', function () {
    Notification::fake();

    $user = User::factory()->create(['role' => 'user']);
    $user->markEmailAsVerified();

    $this->actingAs($user, 'admin');

    $this->get(route('admin.messages.index'))->assertForbidden();
    $this->post(route('admin.messages.store'), [])->assertForbidden();

    Notification::assertNothingSent();
});
