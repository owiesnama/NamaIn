<?php

use App\Enums\ChequeStatus;
use App\Models\Cheque;
use App\Models\User;
use App\Notifications\ChequeDueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    config([
        'app.admins' => ['platform-admin@namain.test'],
        'app.cheques_notify_before_days' => 3,
    ]);

    $this->admin = User::factory()->create(['email' => 'platform-admin@namain.test']);
});

test('it notifies platform admins of cheques due within the configured window', function () {
    $dueSoon = Cheque::factory()->create([
        'status' => ChequeStatus::Issued,
        'due' => now()->addDay(),
    ]);

    // The scheduler runs without a tenant bound, like production.
    app()->forgetInstance('currentTenant');

    $this->artisan('cheques:notify-for-due')->assertExitCode(0);

    Notification::assertSentTo(
        $this->admin,
        ChequeDueNotification::class,
        fn (ChequeDueNotification $notification) => $notification->cheque->is($dueSoon),
    );
});

test('it respects the configured notify-before window', function () {
    config(['app.cheques_notify_before_days' => 1]);

    Cheque::factory()->create([
        'status' => ChequeStatus::Issued,
        'due' => now()->addDays(2),
    ]);

    app()->forgetInstance('currentTenant');

    $this->artisan('cheques:notify-for-due')->assertExitCode(0);

    Notification::assertNothingSent();
});

test('it does not notify for cheques far in the future or already settled', function () {
    Cheque::factory()->create([
        'status' => ChequeStatus::Issued,
        'due' => now()->addDays(10),
    ]);

    Cheque::factory()->create([
        'status' => ChequeStatus::Cleared,
        'due' => now()->addDay(),
    ]);

    Cheque::factory()->create([
        'status' => ChequeStatus::Cancelled,
        'due' => now()->addDay(),
    ]);

    app()->forgetInstance('currentTenant');

    $this->artisan('cheques:notify-for-due')->assertExitCode(0);

    Notification::assertNothingSent();
});
