<?php

use App\Jobs\RunBackupJob;
use App\Models\Backup;
use App\Models\BackupSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('shows the backups page', function () {
    actingAsSuperAdmin();

    $this->get(route('admin.backups.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Backups')
            ->has('backups')
            ->has('tenants')
            ->has('settings')
        );
});

it('lists existing backups', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Backup::create([
        'type' => 'full',
        'format' => 'dump',
        'filename' => 'full_test.dump',
        'status' => 'completed',
        'created_by' => $admin->id,
    ]);

    actingAsSuperAdmin($admin);

    $this->get(route('admin.backups.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('backups.data', 1)
        );
});

it('filters backups by type', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $tenant = Tenant::factory()->create();

    Backup::create(['type' => 'full', 'format' => 'dump', 'filename' => 'full.dump', 'status' => 'completed']);
    Backup::create(['type' => 'tenant', 'format' => 'sql', 'filename' => 'tenant.sql', 'status' => 'completed', 'tenant_id' => $tenant->id]);

    actingAsSuperAdmin($admin);

    $this->get(route('admin.backups.index', ['type' => 'tenant']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('backups.data', 1)
            ->where('backups.data.0.type', 'tenant')
        );
});

it('queues a tenant backup', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $tenant = Tenant::factory()->create();

    actingAsSuperAdmin($admin);

    $this->post(route('admin.backups.store'), [
        'type' => 'tenant',
        'format' => 'sql',
        'tenant_id' => $tenant->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('backups', [
        'type' => 'tenant',
        'format' => 'sql',
        'tenant_id' => $tenant->id,
        'status' => 'pending',
        'created_by' => $admin->id,
    ]);

    Queue::assertPushed(RunBackupJob::class);
});

it('queues a full database backup', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    actingAsSuperAdmin($admin);

    $this->post(route('admin.backups.store'), [
        'type' => 'full',
        'format' => 'dump',
    ])->assertRedirect();

    $this->assertDatabaseHas('backups', [
        'type' => 'full',
        'format' => 'dump',
        'status' => 'pending',
        'created_by' => $admin->id,
    ]);

    Queue::assertPushed(RunBackupJob::class);
});

it('validates store request requires type', function () {
    actingAsSuperAdmin();

    $this->post(route('admin.backups.store'), [
        'format' => 'sql',
    ])->assertSessionHasErrors('type');
});

it('validates tenant_id required for tenant type', function () {
    actingAsSuperAdmin();

    $this->post(route('admin.backups.store'), [
        'type' => 'tenant',
        'format' => 'sql',
    ])->assertSessionHasErrors('tenant_id');
});

it('deletes a backup and its file', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $backup = Backup::create([
        'type' => 'full',
        'format' => 'dump',
        'filename' => 'test_delete.dump',
        'status' => 'completed',
        'created_by' => $admin->id,
    ]);

    actingAsSuperAdmin($admin);

    $this->delete(route('admin.backups.destroy', $backup))
        ->assertRedirect();

    $this->assertDatabaseMissing('backups', ['id' => $backup->id]);
});

it('updates backup settings', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    actingAsSuperAdmin($admin);

    $this->put(route('admin.backups.settings'), [
        'is_enabled' => true,
        'frequency' => 'weekly',
        'cron_expression' => null,
        'retention_count' => 10,
    ])->assertRedirect();

    $settings = BackupSetting::resolve();
    expect($settings->is_enabled)->toBeTrue()
        ->and($settings->frequency)->toBe('weekly')
        ->and($settings->retention_count)->toBe(10);
});

it('validates cron expression required for custom frequency', function () {
    actingAsSuperAdmin();

    $this->put(route('admin.backups.settings'), [
        'is_enabled' => true,
        'frequency' => 'custom',
        'cron_expression' => null,
        'retention_count' => 5,
    ])->assertSessionHasErrors('cron_expression');
});

it('rejects non-admin users', function () {
    $user = User::factory()->create();

    test()->actingAs($user, 'admin');

    $this->get(route('admin.backups.index'))
        ->assertForbidden();
});
