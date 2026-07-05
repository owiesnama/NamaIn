<?php

use App\Models\Device;
use App\Models\ReconciliationItem;
use App\Models\Tenant;
use App\Queries\PilotHealthQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function pilotTenant(): Tenant
{
    return Tenant::create(['name' => 'Pilot Store', 'slug' => 'pilot-'.uniqid(), 'is_active' => true, 'offline_enabled' => true]);
}

it('renders the pilot health page with SLOs for a selected tenant', function () {
    actingAsSuperAdmin();
    $tenant = pilotTenant();

    test()->get(route('admin.pilot-health.index', ['tenant' => $tenant->id]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/PilotHealth/Index')
            ->has('slos')
            ->where('slos.duplicated_sales', 0));
});

it('computes sale latency p95 from client_pushed_at', function () {
    $tenant = pilotTenant();
    $device = Device::factory()->create(['tenant_id' => $tenant->id]);

    // Two pushes: 10s and 30s from worker start to server landing → p95 = 30.
    foreach ([10, 30] as $latency) {
        DB::table('sync_logs')->insert([
            'tenant_id' => $tenant->id,
            'device_id' => $device->id,
            'endpoint' => 'push',
            'mutation_count' => 1,
            'applied_count' => 1,
            'rejected_count' => 0,
            'client_pushed_at' => now()->subSeconds($latency),
            'created_at' => now(),
        ]);
    }

    $slos = app(PilotHealthQuery::class)->get($tenant->id, now()->subDay(), now()->addDay());

    expect($slos['sale_latency_p95_seconds'])->toBeGreaterThanOrEqual(29.0);
    expect($slos['sale_latency_p95_seconds'])->toBeLessThanOrEqual(31.0);
});

it('computes resolution p95, open items and crash-free rate', function () {
    $tenant = pilotTenant();
    Device::factory()->create(['tenant_id' => $tenant->id, 'crash_count' => 1, 'session_count' => 100]);

    ReconciliationItem::factory()->create([
        'tenant_id' => $tenant->id,
        'detected_at' => now()->subHours(10),
        'resolved_at' => now(),
        'status' => ReconciliationItem::STATUS_RESOLVED,
    ]);
    ReconciliationItem::factory()->create(['tenant_id' => $tenant->id]);

    $slos = app(PilotHealthQuery::class)->get($tenant->id, now()->subDay(), now()->addDay());

    expect($slos['resolution_p95_hours'])->toBeGreaterThanOrEqual(9.0);
    expect($slos['open_items'])->toBe(1);
    expect($slos['crash_free_rate'])->toBe(0.99);
});

it('counts applied sales and duplicates from the idempotency ledger', function () {
    $tenant = pilotTenant();

    foreach (range(1, 3) as $i) {
        DB::table('sync_idempotency')->insert([
            'tenant_id' => $tenant->id,
            'idempotency_key' => (string) Str::ulid(),
            'mutation_type' => 'sale.create',
            'result_public_id' => strtolower((string) Str::ulid()),
            'status' => 'applied',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $slos = app(PilotHealthQuery::class)->get($tenant->id, now()->subDay(), now()->addDay());

    expect($slos['applied_sales'])->toBe(3);
    expect($slos['duplicated_sales'])->toBe(0);
});
