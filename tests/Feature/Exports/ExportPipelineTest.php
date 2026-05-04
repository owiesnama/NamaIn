<?php

use App\Enums\ExportStatus;
use App\Jobs\GenerateExportJob;
use App\Models\ExportLog;
use App\Models\ImportLog;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('POST /exports creates export log and dispatches job', function () {
    Queue::fake();
    actingAsTenantUser(role: 'owner');

    $response = $this->post(route('exports.store'), [
        'export_key' => 'customers',
        'format' => 'xlsx',
        'filters' => [],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('export_logs', [
        'export_key' => 'customers',
        'format' => 'xlsx',
        'status' => 'queued',
    ]);

    Queue::assertPushed(GenerateExportJob::class);
});

test('POST /exports rejects invalid export key', function () {
    actingAsTenantUser(role: 'owner');

    $response = $this->post(route('exports.store'), [
        'export_key' => 'invalid-key',
        'format' => 'xlsx',
    ]);

    $response->assertSessionHasErrors('export_key');
});

test('export log can transition through statuses', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);

    $exportLog = ExportLog::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'export_key' => 'customers',
        'format' => 'xlsx',
    ]);

    expect($exportLog->status)->toBe(ExportStatus::Queued);

    $exportLog->markProcessing();
    expect($exportLog->fresh()->status)->toBe(ExportStatus::Processing);

    $exportLog->markCompleted('exports/test.xlsx', 'test.xlsx');
    $exportLog = $exportLog->fresh();
    expect($exportLog->status)->toBe(ExportStatus::Completed);
    expect($exportLog->path)->toBe('exports/test.xlsx');
    expect($exportLog->expires_at)->not->toBeNull();
});

test('export log marks as failed with message', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);

    $exportLog = ExportLog::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'export_key' => 'customers',
        'format' => 'xlsx',
    ]);

    $exportLog->markFailed('Something went wrong');

    $exportLog = $exportLog->fresh();
    expect($exportLog->status)->toBe(ExportStatus::Failed);
    expect($exportLog->failure_message)->toBe('Something went wrong');
});

test('completed export can be downloaded by owner', function () {
    Storage::fake('local');
    Storage::put('exports/test.xlsx', 'test content');

    actingAsTenantUser(role: 'owner');
    $user = auth()->user();

    $exportLog = ExportLog::create([
        'user_id' => $user->id,
        'tenant_id' => $user->current_tenant_id,
        'export_key' => 'customers',
        'format' => 'xlsx',
        'status' => ExportStatus::Completed,
        'path' => 'exports/test.xlsx',
        'filename' => 'test.xlsx',
        'expires_at' => now()->addDays(90),
    ]);

    $response = $this->get(route('exports.download', $exportLog));

    $response->assertOk();
});

test('user cannot download another users export unless manager', function () {
    $tenant = Tenant::factory()->create();
    seedTenantRoles($tenant);
    app()->instance('currentTenant', $tenant);

    $owner = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'staff')->first();
    $otherUser = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($otherUser, ['role' => 'staff', 'role_id' => $staffRole->id, 'is_active' => true]);

    $exportLog = ExportLog::create([
        'user_id' => $owner->id,
        'tenant_id' => $tenant->id,
        'export_key' => 'customers',
        'format' => 'xlsx',
        'status' => ExportStatus::Completed,
        'path' => 'exports/test.xlsx',
        'filename' => 'test.xlsx',
        'expires_at' => now()->addDays(90),
    ]);

    $otherUser->markEmailAsVerified();
    URL::defaults(['tenant' => $tenant->slug]);

    $response = $this->actingAs($otherUser)->get(route('exports.download', $exportLog));

    $response->assertForbidden();
});

test('export history page loads', function () {
    actingAsTenantUser(role: 'owner');

    $response = $this->get(route('exports.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Exports/Index'));
});

test('shared operations restore active imports and exports after refresh', function () {
    actingAsTenantUser(role: 'owner');
    $user = auth()->user();
    $tenantId = $user->current_tenant_id;
    $otherUser = User::factory()->create(['current_tenant_id' => $tenantId]);

    $exportLog = ExportLog::create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'export_key' => 'customers',
        'format' => 'xlsx',
        'status' => ExportStatus::Processing,
        'updated_at' => now()->subMinute(),
    ]);

    $importLog = ImportLog::create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'import_type' => 'products',
        'original_filename' => 'products.csv',
        'stored_path' => 'imports/products.csv',
        'status' => ExportStatus::Queued,
        'updated_at' => now(),
    ]);

    ExportLog::create([
        'tenant_id' => $tenantId,
        'user_id' => $otherUser->id,
        'export_key' => 'suppliers',
        'format' => 'xlsx',
        'status' => ExportStatus::Processing,
    ]);

    ExportLog::create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'export_key' => 'products',
        'format' => 'xlsx',
        'status' => ExportStatus::Completed,
        'updated_at' => now()->subHour(),
    ]);

    $response = $this->get(route('exports.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('operations', 2)
        ->where('operations', fn ($operations) => collect($operations)->contains(
            fn (array $operation) => $operation['kind'] === 'export'
                && $operation['id'] === $exportLog->id
                && $operation['label'] === 'customers'
                && $operation['status'] === ExportStatus::Processing->value
        ) && collect($operations)->contains(
            fn (array $operation) => $operation['kind'] === 'import'
                && $operation['id'] === "import-{$importLog->id}"
                && $operation['label'] === 'products'
                && $operation['status'] === ExportStatus::Queued->value
        ))
    );
});

test('shared operations include recent finished jobs after a missed websocket update', function () {
    actingAsTenantUser(role: 'owner');
    $user = auth()->user();
    $tenantId = $user->current_tenant_id;

    $exportLog = ExportLog::create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'export_key' => 'customers',
        'format' => 'xlsx',
        'status' => ExportStatus::Completed,
        'path' => 'exports/customers.xlsx',
        'filename' => 'customers.xlsx',
        'expires_at' => now()->addDays(90),
        'updated_at' => now()->subMinute(),
    ]);

    $importLog = ImportLog::create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'import_type' => 'products',
        'original_filename' => 'products.csv',
        'stored_path' => 'imports/products.csv',
        'status' => ExportStatus::Failed,
        'failure_message' => 'Invalid header row.',
        'updated_at' => now(),
    ]);

    ImportLog::create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'import_type' => 'suppliers',
        'original_filename' => 'suppliers.csv',
        'stored_path' => 'imports/suppliers.csv',
        'status' => ExportStatus::Completed,
        'updated_at' => now()->subHour(),
    ]);

    $response = $this->get(route('exports.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('operations', 2)
        ->where('operations', fn ($operations) => collect($operations)->contains(
            fn (array $operation) => $operation['kind'] === 'export'
                && $operation['id'] === $exportLog->id
                && $operation['status'] === ExportStatus::Completed->value
                && $operation['filename'] === 'customers.xlsx'
        ) && collect($operations)->contains(
            fn (array $operation) => $operation['kind'] === 'import'
                && $operation['id'] === "import-{$importLog->id}"
                && $operation['status'] === ExportStatus::Failed->value
                && $operation['failure_message'] === 'Invalid header row.'
        ))
    );
});

test('prune command deletes expired exports', function () {
    Storage::fake('local');
    Storage::put('exports/old.xlsx', 'old content');

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);

    ExportLog::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'export_key' => 'customers',
        'format' => 'xlsx',
        'status' => ExportStatus::Completed,
        'path' => 'exports/old.xlsx',
        'filename' => 'old.xlsx',
        'expires_at' => now()->subDay(),
    ]);

    ExportLog::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'export_key' => 'products',
        'format' => 'xlsx',
        'status' => ExportStatus::Completed,
        'path' => 'exports/new.xlsx',
        'filename' => 'new.xlsx',
        'expires_at' => now()->addDays(30),
    ]);

    $this->artisan('exports:prune')
        ->assertExitCode(0);

    expect(ExportLog::withoutGlobalScopes()->count())->toBe(1);
    Storage::assertMissing('exports/old.xlsx');
});
