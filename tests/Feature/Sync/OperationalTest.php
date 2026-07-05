<?php

use App\Enums\DeviceStatus;
use App\Models\Category;
use App\Models\Expense;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage as Disk;
use Illuminate\Support\Str;

require_once __DIR__.'/SyncPushHelpers.php';

it('writes a sync_log row for a push with counts, latency and client_pushed_at', function () {
    $env = pushEnvironment();
    $clientPushedAt = now()->subSeconds(2)->toIso8601String();

    test()->postJson('/api/sync/v1/push', [
        'protocol' => 1,
        'client_pushed_at' => $clientPushedAt,
        'mutations' => [customerMutation($env, strtolower((string) Str::ulid()))],
    ], ['Authorization' => "Bearer {$env['token']}"])->assertOk();

    $log = DB::table('sync_logs')->where('endpoint', 'push')->first();
    expect($log)->not->toBeNull();
    expect((int) $log->device_id)->toBe($env['device']->id);
    expect((int) $log->mutation_count)->toBe(1);
    expect((int) $log->applied_count)->toBe(1);
    expect((int) $log->rejected_count)->toBe(0);
    expect($log->duration_ms)->not->toBeNull();
    expect($log->client_pushed_at)->not->toBeNull();
});

it('writes a sync_log row for a pull with the cursor window', function () {
    $env = pushEnvironment();

    test()->getJson('/api/sync/v1/pull?cursor=0', ['Authorization' => "Bearer {$env['token']}"])->assertOk();

    $log = DB::table('sync_logs')->where('endpoint', 'pull')->first();
    expect($log)->not->toBeNull();
    expect((int) $log->cursor_from)->toBe(0);
    expect($log->cursor_to)->not->toBeNull();
});

it('records device backlog reported on push', function () {
    $env = pushEnvironment();
    $oldest = now()->subMinutes(10)->toIso8601String();

    test()->postJson('/api/sync/v1/push', [
        'protocol' => 1,
        'app_version' => '1.2.3',
        'pending_count' => 7,
        'oldest_pending_at' => $oldest,
        'mutations' => [customerMutation($env, strtolower((string) Str::ulid()))],
    ], ['Authorization' => "Bearer {$env['token']}"])->assertOk();

    $device = $env['device']->fresh();
    expect((int) $device->pending_count)->toBe(7);
    expect($device->app_version)->toBe('1.2.3');
    expect($device->oldest_pending_at)->not->toBeNull();
    expect($device->last_push_at)->not->toBeNull();
});

it('updates device health columns on heartbeat', function () {
    $env = pushEnvironment();

    test()->postJson('/api/sync/v1/heartbeat', [
        'pending_count' => 3,
        'oldest_pending_at' => now()->subMinutes(5)->toIso8601String(),
        'app_version' => '1.0.1',
        'crash_count' => 2,
        'session_count' => 41,
    ], ['Authorization' => "Bearer {$env['token']}"])
        ->assertOk()
        ->assertJsonPath('protocol', 1);

    $device = $env['device']->fresh();
    expect((int) $device->pending_count)->toBe(3);
    expect((int) $device->crash_count)->toBe(2);
    expect((int) $device->session_count)->toBe(41);
    expect($device->app_version)->toBe('1.0.1');
});

it('stores an attachment and links it to an existing expense', function () {
    Disk::fake('local');
    $env = pushEnvironment();
    $receiptPublicId = strtolower((string) Str::ulid());

    // The expense lands first (the documented common order).
    $category = Category::create(['name' => 'Fuel', 'type' => 'expense']);
    pushAs($env, [[
        'idempotency_key' => (string) Str::ulid(),
        'type' => 'expense.create',
        'public_id' => strtolower((string) Str::ulid()),
        'actor' => $env['actor']->public_id,
        'occurred_at' => now()->toIso8601String(),
        'payload' => [
            'title' => 'Fuel', 'amount' => 5000, 'expensed_at' => '2026-07-03', 'notes' => null,
            'categories' => [$category->public_id], 'treasury_account' => $env['drawer']->public_id,
            'receipt_public_id' => $receiptPublicId,
        ],
    ]])->assertOk();

    test()->post('/api/sync/v1/attachments', [
        'receipt_public_id' => $receiptPublicId,
        'file' => UploadedFile::fake()->image('receipt.jpg'),
    ], ['Authorization' => "Bearer {$env['token']}", 'Accept' => 'application/json'])->assertCreated();

    $expense = Expense::where('receipt_public_id', $receiptPublicId)->first();
    expect($expense->receipt_path)->not->toBeNull();
    Disk::disk('local')->assertExists($expense->receipt_path);
});

it('links an attachment uploaded before the expense lands', function () {
    Disk::fake('local');
    $env = pushEnvironment();
    $receiptPublicId = strtolower((string) Str::ulid());

    // Attachment first — no expense to link yet.
    test()->post('/api/sync/v1/attachments', [
        'receipt_public_id' => $receiptPublicId,
        'file' => UploadedFile::fake()->create('receipt.pdf', 200, 'application/pdf'),
    ], ['Authorization' => "Bearer {$env['token']}", 'Accept' => 'application/json'])->assertCreated();

    $category = Category::create(['name' => 'Fuel', 'type' => 'expense']);
    pushAs($env, [[
        'idempotency_key' => (string) Str::ulid(),
        'type' => 'expense.create',
        'public_id' => strtolower((string) Str::ulid()),
        'actor' => $env['actor']->public_id,
        'occurred_at' => now()->toIso8601String(),
        'payload' => [
            'title' => 'Fuel', 'amount' => 5000, 'expensed_at' => '2026-07-03', 'notes' => null,
            'categories' => [$category->public_id], 'treasury_account' => $env['drawer']->public_id,
            'receipt_public_id' => $receiptPublicId,
        ],
    ]])->assertOk();

    $expense = Expense::where('receipt_public_id', $receiptPublicId)->first();
    expect($expense->receipt_path)->not->toBeNull();
});

it('rejects an oversized or wrong-type attachment', function () {
    Disk::fake('local');
    $env = pushEnvironment();

    test()->post('/api/sync/v1/attachments', [
        'receipt_public_id' => strtolower((string) Str::ulid()),
        'file' => UploadedFile::fake()->create('too-big.pdf', 6000, 'application/pdf'), // > 5 MB
    ], ['Authorization' => "Bearer {$env['token']}", 'Accept' => 'application/json'])->assertStatus(422);

    test()->post('/api/sync/v1/attachments', [
        'receipt_public_id' => strtolower((string) Str::ulid()),
        'file' => UploadedFile::fake()->create('note.txt', 10, 'text/plain'),
    ], ['Authorization' => "Bearer {$env['token']}", 'Accept' => 'application/json'])->assertStatus(422);
});

it('requires the sync:attach ability for attachments', function () {
    Disk::fake('local');
    $env = pushEnvironment();
    $limited = $env['device']->createToken('limited', ['sync:pull'])->plainTextToken;

    test()->post('/api/sync/v1/attachments', [
        'receipt_public_id' => strtolower((string) Str::ulid()),
        'file' => UploadedFile::fake()->image('r.png'),
    ], ['Authorization' => "Bearer {$limited}", 'Accept' => 'application/json'])->assertForbidden();
});

it('detaches a device: revokes its token and revokes the device', function () {
    $env = pushEnvironment();

    test()->postJson('/api/sync/v1/detach', [], ['Authorization' => "Bearer {$env['token']}"])
        ->assertOk()
        ->assertJsonPath('status', 'revoked');

    // The device is revoked and its own token is deleted.
    expect($env['device']->fresh()->status)->toBe(DeviceStatus::Revoked);
    expect($env['device']->tokens()->count())->toBe(0);

    // Any further sync call gets the first-class device_revoked status.
    test()->getJson('/api/sync/v1/pull?cursor=0', ['Authorization' => "Bearer {$env['token']}"])
        ->assertStatus(403)
        ->assertJsonPath('error', 'device_revoked');
});

it('exposes the named sync rate limiter', function () {
    expect(app(RateLimiter::class)->limiter('sync'))->not->toBeNull();
});
