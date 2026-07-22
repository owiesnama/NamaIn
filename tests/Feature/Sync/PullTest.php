<?php

use App\Actions\Sync\EnrollDeviceAction;
use App\Actions\Sync\ProvisionDeviceAction;
use App\Enums\StorageType;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\SyncSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage as Disk;

/**
 * @return array{device: Device, token: string, storage: Storage}
 */
function pullEnvironment(): array
{
    seedTenantRoles(app('currentTenant'));

    $storage = Storage::create([
        'name' => 'Front Store',
        'address' => 'x',
        'type' => StorageType::SALE_POINT,
    ]);

    $enrollment = app(EnrollDeviceAction::class)->handle($storage, 'Front counter');
    $provisioned = app(ProvisionDeviceAction::class)->handle($enrollment['pairing_code']);

    return [
        'device' => $provisioned['device'],
        'token' => $provisioned['token'],
        'storage' => $storage,
    ];
}

function currentCursor(int $tenantId): int
{
    return max(0, (int) DB::table('tenant_sync_state')->where('tenant_id', $tenantId)->value('next_seq') - 1);
}

it('delivers post-cursor changes collapsed to the latest live payload', function () {
    $environment = pullEnvironment();
    $device = $environment['device'];

    Product::create(['name' => 'Before', 'cost' => 1, 'price' => 2, 'currency' => 'SDG']);
    $cursor = currentCursor($device->tenant_id);

    $product = Product::create(['name' => 'Cola', 'cost' => 7.25, 'price' => 10.5, 'currency' => 'SDG']);
    $product->update(['price' => 12]);

    $response = $this->getJson("/api/sync/v1/pull?cursor={$cursor}", ['Authorization' => "Bearer {$environment['token']}"]);

    $response->assertOk()
        ->assertJsonPath('protocol', 1)
        ->assertJsonPath('has_more', false);

    $changes = collect($response->json('changes'));
    $productChanges = $changes->where('table', 'products');

    // create + update collapse into one entry carrying the live payload
    expect($productChanges)->toHaveCount(1);
    $entry = $productChanges->first();
    expect($entry['public_id'])->toBe($product->public_id);
    expect($entry['op'])->toBe('update');
    expect($entry['payload']['price'])->toBe(1200);
    expect($entry['payload']['name'])->toBe('Cola');
    expect($entry['payload'])->not->toHaveKey('id');

    expect($changes->pluck('public_id'))->not->toContain(Product::where('name', 'Before')->first()->public_id);
    expect($response->json('next_cursor'))->toBe(currentCursor($device->tenant_id));

    // a second pull from next_cursor is empty — no double-apply
    $again = $this->getJson('/api/sync/v1/pull?cursor='.$response->json('next_cursor'), ['Authorization' => "Bearer {$environment['token']}"]);
    $again->assertOk()->assertJsonPath('has_more', false);
    expect($again->json('changes'))->toBe([]);
    expect($again->json('next_cursor'))->toBe($response->json('next_cursor'));

    $device->refresh();
    expect($device->last_pull_at)->not->toBeNull();
    expect((int) $device->last_acked_seq)->toBe($response->json('next_cursor'));
});

it('continues seamlessly from the snapshot cursor — no gap, no double-apply', function () {
    Disk::fake('local');
    $environment = pullEnvironment();

    $before = Product::create(['name' => 'In snapshot', 'cost' => 1, 'price' => 2, 'currency' => 'SDG']);

    $snapshotId = $this->postJson('/api/sync/v1/snapshot', [], ['Authorization' => "Bearer {$environment['token']}"])
        ->json('snapshot_id');
    $manifestCursor = SyncSnapshot::where('public_id', $snapshotId)->firstOrFail()->cursor;

    $after = Product::create(['name' => 'After snapshot', 'cost' => 1, 'price' => 3, 'currency' => 'SDG']);

    $response = $this->getJson("/api/sync/v1/pull?cursor={$manifestCursor}", ['Authorization' => "Bearer {$environment['token']}"]);
    $response->assertOk();

    $productIds = collect($response->json('changes'))->where('table', 'products')->pluck('public_id');

    expect($productIds)->toContain($after->public_id);
    expect($productIds)->not->toContain($before->public_id);
});

it('filters scoped entities to the device register while stocks stay tenant-wide', function () {
    $environment = pullEnvironment();
    $device = $environment['device'];

    $otherStorage = Storage::create(['name' => 'Other Store', 'address' => 'x', 'type' => StorageType::SALE_POINT]);
    $otherEnrollment = app(EnrollDeviceAction::class)->handle($otherStorage, 'Other counter');
    $otherRegister = $otherEnrollment['device']->register;

    $product = Product::create(['name' => 'Cola', 'cost' => 1, 'price' => 2, 'currency' => 'SDG']);
    $cursor = currentCursor($device->tenant_id);

    $mine = Invoice::factory()->create(['register_id' => $device->register_id]);
    $theirs = Invoice::factory()->create(['register_id' => $otherRegister->id]);
    $otherStorage->addStock($product->id, 5, 'initial');

    $response = $this->getJson("/api/sync/v1/pull?cursor={$cursor}", ['Authorization' => "Bearer {$environment['token']}"]);
    $response->assertOk();

    $changes = collect($response->json('changes'));
    $invoiceIds = $changes->where('table', 'invoices')->pluck('public_id');

    expect($invoiceIds)->toContain($mine->public_id);
    expect($invoiceIds)->not->toContain($theirs->public_id);

    $mineEntry = $changes->where('table', 'invoices')->firstWhere('public_id', $mine->public_id);
    expect($mineEntry['payload']['serial_number'])->toBe($mine->serial_number);
    expect($mineEntry['payload']['register'])->toBe($device->register->public_id);

    // stock level of ANOTHER storage is still delivered (replenishment hints)
    $stockEntries = $changes->where('table', 'stocks');
    expect($stockEntries)->toHaveCount(1);
    expect($stockEntries->first()['payload']['storage'])->toBe($otherStorage->public_id);
    expect($stockEntries->first()['payload']['product'])->toBe($product->public_id);

    // the skipped invoice still advances the cursor
    expect($response->json('next_cursor'))->toBe(currentCursor($device->tenant_id));
});

it('delivers deletions as tombstones with a null payload', function () {
    $environment = pullEnvironment();

    $customer = Customer::create(['name' => 'Sara', 'address' => 'x', 'phone_number' => '099']);
    $cursor = currentCursor($environment['device']->tenant_id);

    $customer->delete();

    $response = $this->getJson("/api/sync/v1/pull?cursor={$cursor}", ['Authorization' => "Bearer {$environment['token']}"]);
    $response->assertOk();

    $entry = collect($response->json('changes'))->where('table', 'customers')->firstWhere('public_id', $customer->public_id);

    expect($entry['op'])->toBe('delete');
    expect($entry['payload'])->toBeNull();
});

it('returns 409 cursor_expired when the cursor predates retained history', function () {
    $environment = pullEnvironment();
    $device = $environment['device'];

    Product::create(['name' => 'A', 'cost' => 1, 'price' => 2, 'currency' => 'SDG']);
    Product::create(['name' => 'B', 'cost' => 1, 'price' => 2, 'currency' => 'SDG']);

    // simulate compaction pruning the head of the log
    $pruneBelow = (int) DB::table('change_log')->where('tenant_id', $device->tenant_id)->max('seq');
    DB::table('change_log')->where('tenant_id', $device->tenant_id)->where('seq', '<', $pruneBelow)->delete();

    $this->getJson('/api/sync/v1/pull?cursor=0', ['Authorization' => "Bearer {$environment['token']}"])
        ->assertStatus(409)
        ->assertJsonPath('error', 'cursor_expired')
        ->assertJsonPath('min_cursor', $pruneBelow);
});

it('answers 426 upgrade_required for an outdated protocol', function () {
    $environment = pullEnvironment();

    $this->getJson('/api/sync/v1/pull?cursor=0', [
        'Authorization' => "Bearer {$environment['token']}",
        'X-Sync-Protocol' => '0',
    ])
        ->assertStatus(426)
        ->assertJsonPath('error', 'upgrade_required')
        ->assertJsonStructure(['error', 'min_protocol', 'min_app_version']);
});

it('rejects pull from a token missing the sync:pull ability', function () {
    $environment = pullEnvironment();
    $limited = $environment['device']->createToken('limited', ['sync:snapshot'])->plainTextToken;

    $this->getJson('/api/sync/v1/pull?cursor=0', ['Authorization' => "Bearer {$limited}"])
        ->assertForbidden();
});

it('runs behind the named sync rate limiter', function () {
    $route = app('router')->getRoutes()->getByName('sync.pull');
    $resolved = app('router')->gatherRouteMiddleware($route);

    $throttled = collect($resolved)->contains(
        fn ($middleware) => is_string($middleware) && str_ends_with($middleware, 'ThrottleRequests:sync')
    );

    expect($throttled)->toBeTrue();
});
