<?php

use App\Enums\DeviceHealth;
use App\Enums\DeviceStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage as Disk;
use Illuminate\Support\Str;

require_once __DIR__.'/SyncPushHelpers.php';

/**
 * Insert a raw change-log entry for a tenant at an explicit seq (test-only; the
 * production path always goes through ChangeLog::record).
 */
function logEntry(int $tenantId, int $seq, string $publicId, string $op, string $changedAt): void
{
    DB::table('change_log')->insert([
        'tenant_id' => $tenantId,
        'seq' => $seq,
        'table_name' => 'customers',
        'public_id' => $publicId,
        'operation' => $op,
        'source_device_id' => null,
        'actor_user_id' => null,
        'changed_at' => $changedAt,
    ]);
}

it('captures clock skew from X-Client-Time and flags the device skewed', function () {
    $env = pushEnvironment();

    test()->postJson('/api/sync/v1/push', [
        'protocol' => 1,
        'app_version' => '1.0.0',
        'mutations' => [customerMutation($env, strtolower((string) Str::ulid()))],
    ], [
        'Authorization' => "Bearer {$env['token']}",
        'X-Client-Time' => now()->subSeconds(600)->toIso8601String(),
    ])->assertOk();

    $device = $env['device']->fresh();
    expect($device->clock_skew_seconds)->toBeGreaterThanOrEqual(590);
    expect($device->health())->toBe(DeviceHealth::Skewed);
});

it('returns 409 cursor_expired when the backlog exceeds the live-row count', function () {
    $env = pushEnvironment();
    $tenantId = $env['device']->tenant_id;
    $publicId = strtolower((string) Str::ulid());

    // One live row, but a backlog past BOTH the live-row ratio and the absolute
    // floor (500) → replaying the backlog is dearer than a fresh snapshot.
    // Small backlogs stay incremental (see PullTest) — field-caught: the
    // ratio alone bounced a same-day device into re-bootstrap mid-shift.
    $base = (int) DB::table('change_log')->where('tenant_id', $tenantId)->max('seq') + 1;
    logEntry($tenantId, $base, $publicId, 'create', now()->toDateTimeString());
    foreach (range(1, 501) as $i) {
        logEntry($tenantId, $base + $i, $publicId, 'update', now()->toDateTimeString());
    }

    // A device past bootstrap pulls from a real cursor; its backlog now dwarfs
    // the tenant's live-row count, so the server tells it to re-snapshot.
    app('auth')->forgetGuards();
    test()->getJson('/api/sync/v1/pull?cursor=1', ['Authorization' => "Bearer {$env['token']}"])
        ->assertStatus(409)
        ->assertJsonPath('error', 'cursor_expired');
});

it('compacts superseded entries older than the floor and below the min cursor only', function () {
    $env = pushEnvironment();
    $tenantId = $env['device']->tenant_id;
    $env['device']->update(['status' => DeviceStatus::Active, 'last_acked_seq' => 100]);

    $old = strtolower((string) Str::ulid());
    $recent = strtolower((string) Str::ulid());
    $ahead = strtolower((string) Str::ulid());

    // Superseded + old + below cursor → pruned (the older of the pair).
    logEntry($tenantId, 10, $old, 'create', now()->subDays(40)->toDateTimeString());
    logEntry($tenantId, 11, $old, 'update', now()->subDays(40)->toDateTimeString());
    // Superseded but recent → kept (inside the 30-day floor).
    logEntry($tenantId, 12, $recent, 'create', now()->toDateTimeString());
    logEntry($tenantId, 13, $recent, 'update', now()->toDateTimeString());
    // Superseded + old but ABOVE the min cursor → kept (a lagging device may need it).
    logEntry($tenantId, 200, $ahead, 'create', now()->subDays(40)->toDateTimeString());
    logEntry($tenantId, 201, $ahead, 'update', now()->subDays(40)->toDateTimeString());

    test()->artisan('sync:compact-change-log')->assertSuccessful();

    // Only the old, superseded, below-cursor create (seq 10) was pruned.
    expect(DB::table('change_log')->where('seq', 10)->exists())->toBeFalse();
    expect(DB::table('change_log')->where('seq', 11)->exists())->toBeTrue();  // latest kept
    expect(DB::table('change_log')->where('seq', 12)->exists())->toBeTrue();  // recent kept
    expect(DB::table('change_log')->where('seq', 200)->exists())->toBeTrue(); // above cursor kept
});

it('resumes a truncated snapshot download via Range and reassembles the whole archive', function () {
    Disk::fake('local');
    $env = pushEnvironment();

    $snapshotId = test()->postJson('/api/sync/v1/snapshot', [], ['Authorization' => "Bearer {$env['token']}"])
        ->json('snapshot_id');

    $auth = ['Authorization' => "Bearer {$env['token']}"];
    $full = test()->get("/api/sync/v1/snapshot/{$snapshotId}/download", $auth);
    $full->assertOk();
    $whole = $full->streamedContent();
    $size = strlen($whole);
    $split = intdiv($size, 2);

    // Fault: the transport dropped after $split bytes. Resume from there via Range.
    $head = test()->get("/api/sync/v1/snapshot/{$snapshotId}/download", array_merge($auth, ['Range' => 'bytes=0-'.($split - 1)]));
    $tail = test()->get("/api/sync/v1/snapshot/{$snapshotId}/download", array_merge($auth, ['Range' => 'bytes='.$split.'-'.($size - 1)]));

    expect($head->getStatusCode())->toBe(206);
    expect($tail->getStatusCode())->toBe(206);
    expect(hash('sha256', $head->streamedContent().$tail->streamedContent()))->toBe(hash('sha256', $whole));
});

it('re-pulling from an older cursor re-sends identical state (idempotent apply)', function () {
    $env = pushEnvironment();

    test()->postJson('/api/sync/v1/push', [
        'protocol' => 1,
        'mutations' => [customerMutation($env, strtolower((string) Str::ulid()))],
    ], ['Authorization' => "Bearer {$env['token']}"])->assertOk();

    app('auth')->forgetGuards();
    $first = test()->getJson('/api/sync/v1/pull?cursor=0', ['Authorization' => "Bearer {$env['token']}"])->json('changes');
    app('auth')->forgetGuards();
    $second = test()->getJson('/api/sync/v1/pull?cursor=0', ['Authorization' => "Bearer {$env['token']}"])->json('changes');

    expect($second)->toEqual($first);
});
