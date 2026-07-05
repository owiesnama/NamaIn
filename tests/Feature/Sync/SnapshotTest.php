<?php

use App\Actions\Sync\EnrollDeviceAction;
use App\Actions\Sync\ProvisionDeviceAction;
use App\Enums\StorageType;
use App\Enums\SyncSnapshotStatus;
use App\Jobs\GenerateSnapshotJob;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Product;
use App\Models\Storage;
use App\Models\SyncSnapshot;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage as Disk;

beforeEach(function () {
    Disk::fake('local');
});

/**
 * Seed a sale point, enroll + provision a device for it, and return the pieces
 * a snapshot test needs.
 *
 * @return array{device: Device, token: string, storage: Storage}
 */
function snapshotEnvironment(): array
{
    $tenant = app('currentTenant');
    seedTenantRoles($tenant);

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

function snapshotLines(string $directory, string $file): array
{
    $raw = gzdecode(Disk::disk('local')->get("{$directory}/{$file}"));

    return array_map(
        fn (string $line) => json_decode($line, true),
        array_values(array_filter(explode("\n", $raw)))
    );
}

it('generates a snapshot with a consistent cursor, projected entities and manifest counts', function () {
    $environment = snapshotEnvironment();
    $storage = $environment['storage'];
    $device = $environment['device'];

    $category = Category::create(['name' => 'Drinks', 'type' => 'product', 'budget_limit' => 0]);
    $product = Product::create(['name' => 'Cola', 'cost' => 7.25, 'price' => 10.5, 'currency' => 'SDG']);
    $product->categories()->attach($category->id);
    $unit = Unit::create(['product_id' => $product->id, 'name' => 'Bottle', 'conversion_factor' => 1]);
    $storage->addStock($product->id, 20, 'initial');
    Customer::create(['name' => 'Sara', 'address' => 'x', 'phone_number' => '099', 'credit_limit' => 500]);

    // the device register already sold under series INV-SA — the successor must resume it
    DB::table('register_serials')->insert([
        'tenant_id' => $device->tenant_id,
        'register_id' => $device->register_id,
        'series' => 'INV-SA',
        'year' => 2026,
        'last_seq' => 145,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cashier = User::factory()->create();
    app('currentTenant')->users()->attach($cashier, ['role' => 'cashier', 'is_active' => true]);

    $response = $this->postJson('/api/sync/v1/snapshot', [], ['Authorization' => "Bearer {$environment['token']}"]);
    $response->assertStatus(202)->assertJsonStructure(['snapshot_id', 'status']);

    $snapshotId = $response->json('snapshot_id');

    $status = $this->getJson("/api/sync/v1/snapshot/{$snapshotId}", ['Authorization' => "Bearer {$environment['token']}"]);
    $status->assertOk()
        ->assertJsonPath('status', 'ready')
        ->assertJsonStructure(['status', 'download_url', 'manifest_url', 'size', 'cursor']);

    $expectedCursor = (int) DB::table('tenant_sync_state')
        ->where('tenant_id', $device->tenant_id)->value('next_seq') - 1;
    expect($status->json('cursor'))->toBe($expectedCursor);

    $snapshot = SyncSnapshot::where('public_id', $snapshotId)->firstOrFail();
    $manifest = $snapshot->manifest;

    expect($manifest['snapshot_id'])->toBe($snapshotId);
    expect($manifest['tenant'])->toBe(app('currentTenant')->public_id);
    expect($manifest['register'])->toBe($device->register->code);
    expect($manifest['storage'])->toBe($storage->public_id);
    expect($manifest['cursor'])->toBe($expectedCursor);
    expect($manifest['protocol'])->toBe(1);

    $entityCounts = collect($manifest['entities'])->pluck('count', 'table');
    expect($entityCounts['products'])->toBe(1);
    expect($entityCounts['units'])->toBe(1);
    expect($entityCounts['categorizables'])->toBe(1);
    expect($entityCounts['register_serials'])->toBe(1);
    expect($entityCounts['customers'])->toBe(1);
    expect($entityCounts['users'])->toBe(1);
    expect($entityCounts)->toHaveKeys([
        'stocks', 'storages', 'registers', 'treasury_accounts', 'preferences',
        'roles', 'permissions', 'permission_role', 'tenant_user', 'suppliers',
        'customer_advances', 'categories',
    ]);

    $directory = "sync-snapshots/{$device->tenant_id}/{$snapshotId}";

    // FKs cross the wire as public_ids, money as integer minor units, no int id leaks
    $productRow = snapshotLines($directory, 'products.jsonl.gz')[0];
    expect($productRow['public_id'])->toBe($product->public_id);
    expect($productRow)->not->toHaveKey('id');
    expect($productRow)->not->toHaveKey('tenant_id');
    expect($productRow['price'])->toBe(1050);
    expect($productRow['cost'])->toBe(725);

    $unitRow = snapshotLines($directory, 'units.jsonl.gz')[0];
    expect($unitRow['product'])->toBe($product->public_id);
    expect($unitRow)->not->toHaveKey('product_id');

    $stockRow = snapshotLines($directory, 'stocks.jsonl.gz')[0];
    expect($stockRow['storage'])->toBe($storage->public_id);
    expect($stockRow['product'])->toBe($product->public_id);
    expect((float) $stockRow['quantity'])->toBe(20.0);

    $serialRow = snapshotLines($directory, 'register_serials.jsonl.gz')[0];
    expect($serialRow['register'])->toBe($device->register->public_id);
    expect($serialRow['series'])->toBe('INV-SA');
    expect($serialRow['last_seq'])->toBe(145);

    // local login needs the bcrypt hash and the tenant membership rows
    $userRows = snapshotLines($directory, 'users.jsonl.gz');
    expect($userRows[0]['public_id'])->toBe($cashier->public_id);
    expect($userRows[0]['password'])->toBe($cashier->getRawOriginal('password'));
    expect($userRows[0])->not->toHaveKey('remember_token');
    expect($userRows[0])->not->toHaveKey('two_factor_secret');

    $membershipRows = snapshotLines($directory, 'tenant_user.jsonl.gz');
    expect($membershipRows[0]['user'])->toBe($cashier->public_id);

    expect(Disk::disk('local')->exists("{$directory}/snapshot.tar.gz"))->toBeTrue();
});

it('serves the archive with HTTP range support', function () {
    $environment = snapshotEnvironment();

    $snapshotId = $this->postJson('/api/sync/v1/snapshot', [], ['Authorization' => "Bearer {$environment['token']}"])
        ->json('snapshot_id');

    $full = $this->get("/api/sync/v1/snapshot/{$snapshotId}/download", ['Authorization' => "Bearer {$environment['token']}"]);
    $full->assertOk();
    expect($full->headers->get('Accept-Ranges'))->toBe('bytes');

    $partial = $this->get("/api/sync/v1/snapshot/{$snapshotId}/download", [
        'Authorization' => "Bearer {$environment['token']}",
        'Range' => 'bytes=0-9',
    ]);
    expect($partial->getStatusCode())->toBe(206);
    expect($partial->headers->get('Content-Length'))->toBe('10');
});

it('rejects snapshot access from another tenant device', function () {
    $environment = snapshotEnvironment();

    $snapshotId = $this->postJson('/api/sync/v1/snapshot', [], ['Authorization' => "Bearer {$environment['token']}"])
        ->json('snapshot_id');

    $tenantB = Tenant::create(['name' => 'B Co', 'slug' => 'b-'.uniqid(), 'is_active' => true, 'offline_enabled' => true]);
    app()->instance('currentTenant', $tenantB);
    $storageB = Storage::create([
        'name' => 'B Store', 'address' => 'x', 'type' => StorageType::SALE_POINT, 'tenant_id' => $tenantB->id,
    ]);
    $enrollmentB = app(EnrollDeviceAction::class)->handle($storageB, 'B counter');
    $tokenB = app(ProvisionDeviceAction::class)->handle($enrollmentB['pairing_code'])['token'];

    // each real request resolves its own device; drop the in-test guard cache
    app('auth')->forgetGuards();

    $this->getJson("/api/sync/v1/snapshot/{$snapshotId}", ['Authorization' => "Bearer {$tokenB}"])
        ->assertNotFound();
});

it('rejects snapshot requests from a token missing the ability', function () {
    $environment = snapshotEnvironment();
    $limited = $environment['device']->createToken('limited', ['sync:pull'])->plainTextToken;

    $this->postJson('/api/sync/v1/snapshot', [], ['Authorization' => "Bearer {$limited}"])
        ->assertForbidden();
});

it('prunes expired snapshots and their files', function () {
    $environment = snapshotEnvironment();

    $snapshotId = $this->postJson('/api/sync/v1/snapshot', [], ['Authorization' => "Bearer {$environment['token']}"])
        ->json('snapshot_id');

    $snapshot = SyncSnapshot::where('public_id', $snapshotId)->firstOrFail();
    $snapshot->update(['expires_at' => now()->subHour()]);
    $directory = "sync-snapshots/{$snapshot->tenant_id}/{$snapshot->public_id}";
    expect(Disk::disk('local')->exists("{$directory}/manifest.json"))->toBeTrue();

    $this->artisan('sync:prune-snapshots')->assertSuccessful();

    expect(SyncSnapshot::where('public_id', $snapshotId)->exists())->toBeFalse();
    expect(Disk::disk('local')->exists("{$directory}/manifest.json"))->toBeFalse();
});

it('marks the snapshot failed when generation throws', function () {
    $environment = snapshotEnvironment();
    $device = $environment['device'];

    $snapshot = SyncSnapshot::create([
        'tenant_id' => $device->tenant_id,
        'device_id' => $device->id,
        'expires_at' => now()->addHours(48),
    ]);

    $job = new GenerateSnapshotJob($snapshot);
    $job->failed(new RuntimeException('boom'));

    expect($snapshot->fresh()->status)->toBe(SyncSnapshotStatus::Failed);
});
