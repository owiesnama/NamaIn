<?php

use App\Models\Device;
use App\Models\Preference;
use App\Models\ReconciliationItem;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ReconciliationDigestNotification;
use Illuminate\Support\Facades\Notification;

function digestUser(string $role): User
{
    $tenant = app('currentTenant');
    seedTenantRoles($tenant);
    $roleModel = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', $role)->first();
    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($user, ['role' => $role, 'role_id' => $roleModel->id, 'is_active' => true]);

    return $user;
}

it('emails the digest only to reconciliation.view holders when items are open', function () {
    Notification::fake();
    $owner = digestUser('owner');       // has reconciliation.view
    $cashier = digestUser('cashier');   // does not

    ReconciliationItem::factory()->count(3)->create();

    $this->artisan('reconciliation:digest')->assertSuccessful();

    Notification::assertSentTo($owner, ReconciliationDigestNotification::class, function ($notification) {
        return $notification->summary['total_open'] === 3
            && $notification->summary['by_type'][0]['count'] === 3;
    });
    Notification::assertNotSentTo($cashier, ReconciliationDigestNotification::class);
});

it('does not email when there are no open items or warnings', function () {
    Notification::fake();
    $owner = digestUser('owner');

    ReconciliationItem::factory()->resolved()->create();

    $this->artisan('reconciliation:digest')->assertSuccessful();

    Notification::assertNothingSent();
});

it('binds the tenant locale onto the digest', function () {
    Notification::fake();
    $owner = digestUser('owner');
    Preference::create(['key' => 'locale', 'value' => 'ar']);

    ReconciliationItem::factory()->create();

    $this->artisan('reconciliation:digest')->assertSuccessful();

    Notification::assertSentTo($owner, ReconciliationDigestNotification::class, function ($notification) {
        return $notification->locale === 'ar';
    });
});

it('includes device-health warnings in the digest', function () {
    Notification::fake();
    $owner = digestUser('owner');
    $tenant = app('currentTenant');

    // An active device never seen is offline; a resolved-only inbox alone would send nothing.
    Device::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Front counter',
        'last_seen_at' => null,
    ]);

    $this->artisan('reconciliation:digest')->assertSuccessful();

    Notification::assertSentTo($owner, ReconciliationDigestNotification::class, function ($notification) {
        return count($notification->summary['device_warnings']) === 1;
    });
});
