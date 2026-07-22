<?php

use App\Actions\Pos\OpenPosSessionAction;
use App\Enums\StorageType;
use App\Models\Product;
use App\Models\Role;
use App\Models\Storage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = app('currentTenant');
    seedTenantRoles($this->tenant);

    $ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'owner')->first();
    $this->owner = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->owner, ['role' => 'owner', 'role_id' => $ownerRole->id, 'is_active' => true]);

    $this->storage = Storage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => StorageType::SALE_POINT,
    ]);

    app(OpenPosSessionAction::class)->handle($this->storage, 0, $this->owner);
});

/**
 * Design 03 seam S6: the product grid search used the pgsql-only `ilike`
 * operator, which 500s on sqlite (packaging spike verdict #1). The lookup
 * must branch on the database driver like every other search in the app.
 */
test('the pos product grid search works on the current database driver', function () {
    Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Coca Cola']);
    Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bread']);

    $this->actingAs($this->owner)
        ->get(route('pos.index', ['search' => 'coca']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Session')
            ->has('initialProducts.data', 1)
            ->where('initialProducts.data.0.name', 'Coca Cola'));
});

test('the pos checkout idempotency key is minted with crypto.randomUUID', function () {
    $sessionPage = file_get_contents(resource_path('js/Pages/Pos/Session.vue'));

    expect($sessionPage)->toContain('crypto.randomUUID()')
        ->not->toContain('Date.now().toString()');
});
