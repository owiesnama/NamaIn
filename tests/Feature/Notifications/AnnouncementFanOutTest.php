<?php

use App\Actions\Admin\ResolveAnnouncementAudienceAction;
use App\Jobs\SendAnnouncementJob;
use App\Models\Announcement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Support\Facades\Notification;

function runFanOut(Announcement $announcement): void
{
    (new SendAnnouncementJob($announcement))->handle(new ResolveAnnouncementAudienceAction);
}

function tenantWithUser(string $slug, string $role = 'owner'): array
{
    $tenant = Tenant::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'is_active' => true]);
    seedTenantRoles($tenant);

    $roleModel = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', $role)->first();

    $user = User::factory()->create();
    $tenant->users()->attach($user, ['role' => $role, 'role_id' => $roleModel?->id, 'is_active' => true]);

    return [$tenant, $user, $roleModel];
}

it('fans out to every user for the all audience', function () {
    Notification::fake();

    $users = User::factory()->count(3)->create();
    $announcement = Announcement::factory()->create();

    runFanOut($announcement);

    Notification::assertSentTo($users, AnnouncementNotification::class);

    expect($announcement->refresh())
        ->recipients_count->toBe(User::count())
        ->sent_at->not->toBeNull();
});

it('fans out only to users of the selected tenant', function () {
    Notification::fake();

    [$tenantA, $userA] = tenantWithUser('tenant-a');
    [, $userB] = tenantWithUser('tenant-b');

    $announcement = Announcement::factory()->toTenant($tenantA->id)->create();

    runFanOut($announcement);

    Notification::assertSentTo($userA, AnnouncementNotification::class);
    Notification::assertNotSentTo($userB, AnnouncementNotification::class);

    expect($announcement->refresh()->recipients_count)->toBe(1);
});

it('fans out only to tenant owners for the owners audience', function () {
    Notification::fake();

    [, $owner] = tenantWithUser('tenant-a', 'owner');
    [, $staff] = tenantWithUser('tenant-a', 'staff');

    $announcement = Announcement::factory()->toOwners()->create();

    runFanOut($announcement);

    Notification::assertSentTo($owner, AnnouncementNotification::class);
    Notification::assertNotSentTo($staff, AnnouncementNotification::class);
});

it('fans out only to the selected role within the selected tenant', function () {
    Notification::fake();

    [$tenant, $manager, $managerRole] = tenantWithUser('tenant-a', 'manager');
    [, $owner] = tenantWithUser('tenant-a', 'owner');
    [, $managerElsewhere] = tenantWithUser('tenant-b', 'manager');

    $announcement = Announcement::factory()->toTenantRole($tenant->id, $managerRole->id)->create();

    runFanOut($announcement);

    Notification::assertSentTo($manager, AnnouncementNotification::class);
    Notification::assertNotSentTo($owner, AnnouncementNotification::class);
    Notification::assertNotSentTo($managerElsewhere, AnnouncementNotification::class);
});

it('fans out only to explicitly selected users', function () {
    Notification::fake();

    $selected = User::factory()->count(2)->create();
    $excluded = User::factory()->create();

    $announcement = Announcement::factory()->toUsers($selected->pluck('id')->all())->create();

    runFanOut($announcement);

    Notification::assertSentTo($selected, AnnouncementNotification::class);
    Notification::assertNotSentTo($excluded, AnnouncementNotification::class);
});

it('delivers in-app always and by mail only when requested', function () {
    $user = User::factory()->create();

    $inAppOnly = new AnnouncementNotification(Announcement::factory()->create());
    $withEmail = new AnnouncementNotification(Announcement::factory()->create(['send_email' => true]));

    expect($inAppOnly->via($user))->toBe(['database', 'broadcast'])
        ->and($withEmail->via($user))->toBe(['database', 'broadcast', 'mail']);
});

it('stores the announcement payload in the database notification', function () {
    $user = User::factory()->create();
    $announcement = Announcement::factory()->create(['subject' => 'Big news', 'body' => 'Details here.']);

    $notification = new AnnouncementNotification($announcement);

    expect($notification->toArray($user))->toBe([
        'title' => 'Big news',
        'body' => 'Details here.',
        'url' => '/notifications',
        'meta' => ['announcement_id' => $announcement->id],
    ])->and($notification->databaseType($user))->toBe('announcement');
});
