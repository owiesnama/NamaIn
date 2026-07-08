<?php

use App\Jobs\SendAnnouncementJob;
use App\Models\AdminAuditLog;
use App\Models\Announcement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

it('renders the announcements page for a super admin', function () {
    Announcement::factory()->count(2)->sent()->create();

    actingAsSuperAdmin();

    $this->get(route('admin.announcements.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Announcements/Index')
            ->has('announcements.data', 2)
            ->has('tenants')
            ->has('users'));
});

it('stores an announcement, dispatches the fan-out job, and audit-logs it', function () {
    Bus::fake();

    actingAsSuperAdmin();

    $this->post(route('admin.announcements.store'), [
        'subject' => 'Scheduled maintenance',
        'body' => 'The platform will be briefly unavailable on Friday.',
        'audience_type' => 'all',
    ])->assertRedirect()->assertSessionHas('success');

    $announcement = Announcement::sole();

    expect($announcement->subject)->toBe('Scheduled maintenance')
        ->and($announcement->audience_type->value)->toBe('all')
        ->and($announcement->audience_meta)->toBeNull();

    Bus::assertDispatched(SendAnnouncementJob::class, fn ($job) => $job->announcement->is($announcement));

    expect(AdminAuditLog::where('action', 'announcement.sent')->exists())->toBeTrue();
});

it('stores audience meta per audience type', function (string $audienceType, array $payload, array $expectedMeta) {
    Bus::fake();

    actingAsSuperAdmin();

    $this->post(route('admin.announcements.store'), array_merge([
        'subject' => 'Hello',
        'body' => 'World',
        'audience_type' => $audienceType,
    ], $payload))->assertRedirect()->assertSessionHasNoErrors();

    expect(Announcement::sole()->audience_meta)->toBe($expectedMeta);
})->with([
    'tenant' => [
        'tenant',
        fn () => ['tenant_id' => Tenant::firstOrCreate(['slug' => 'acme'], ['name' => 'Acme', 'is_active' => true])->id],
        fn () => ['tenant_id' => Tenant::where('slug', 'acme')->sole()->id],
    ],
    'users' => [
        'users',
        fn () => ['user_ids' => [
            (User::where('email', 'target@example.com')->first()
                ?? User::factory()->create(['email' => 'target@example.com']))->id,
        ]],
        fn () => ['user_ids' => [User::where('email', 'target@example.com')->sole()->id]],
    ],
]);

it('validates audience-dependent fields', function (array $payload, array $errors) {
    actingAsSuperAdmin();

    $this->post(route('admin.announcements.store'), array_merge([
        'subject' => 'Hello',
        'body' => 'World',
    ], $payload))->assertSessionHasErrors($errors);
})->with([
    'missing subject and body' => [['subject' => '', 'body' => '', 'audience_type' => 'all'], ['subject', 'body']],
    'invalid audience type' => [['audience_type' => 'everyone'], ['audience_type']],
    'tenant audience requires tenant_id' => [['audience_type' => 'tenant'], ['tenant_id']],
    'tenant_role audience requires role_id' => [['audience_type' => 'tenant_role'], ['tenant_id', 'role_id']],
    'users audience requires user_ids' => [['audience_type' => 'users'], ['user_ids']],
]);

it('rejects a role that does not belong to the selected tenant', function () {
    $tenantA = Tenant::create(['name' => 'A', 'slug' => 'tenant-a', 'is_active' => true]);
    $tenantB = Tenant::create(['name' => 'B', 'slug' => 'tenant-b', 'is_active' => true]);
    seedTenantRoles($tenantA);
    seedTenantRoles($tenantB);

    $roleOfB = Role::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->first();

    actingAsSuperAdmin();

    $this->post(route('admin.announcements.store'), [
        'subject' => 'Hello',
        'body' => 'World',
        'audience_type' => 'tenant_role',
        'tenant_id' => $tenantA->id,
        'role_id' => $roleOfB->id,
    ])->assertSessionHasErrors(['role_id']);
});

it('returns the roles of a tenant for the dependent picker', function () {
    $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);
    $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);
    seedTenantRoles($tenant);
    seedTenantRoles($other);

    actingAsSuperAdmin();

    $response = $this->getJson(route('admin.tenants.roles', $tenant))->assertOk();

    $tenantRoleIds = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->pluck('id');

    expect(collect($response->json())->pluck('id')->sort()->values()->all())
        ->toBe($tenantRoleIds->sort()->values()->all());
});

it('forbids non-admins from viewing or sending announcements', function () {
    Bus::fake();

    $user = User::factory()->create(['role' => 'user']);
    $user->markEmailAsVerified();

    $this->actingAs($user, 'admin');

    $this->get(route('admin.announcements.index'))->assertForbidden();
    $this->post(route('admin.announcements.store'), [])->assertForbidden();

    Bus::assertNotDispatched(SendAnnouncementJob::class);
});
