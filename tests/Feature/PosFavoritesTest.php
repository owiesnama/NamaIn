<?php

use App\Actions\Pos\OpenPosSessionAction;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = app('currentTenant');
    seedTenantRoles($this->tenant);

    $ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'owner')->first();
    $cashierRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'cashier')->first();

    $this->owner = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->owner, ['role' => 'owner', 'role_id' => $ownerRole->id, 'is_active' => true]);

    $this->cashier = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->cashier, ['role' => 'cashier', 'role_id' => $cashierRole->id, 'is_active' => true]);
});

test('a user can star and unstar a product as a personal favourite', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->cashier)
        ->postJson(route('pos.favorites.toggle', $product))
        ->assertOk()
        ->assertJson(['favorited' => true]);

    expect($this->cashier->favorites()->where('products.id', $product->id)->exists())->toBeTrue();

    $this->actingAs($this->cashier)
        ->postJson(route('pos.favorites.toggle', $product))
        ->assertOk()
        ->assertJson(['favorited' => false]);

    expect($this->cashier->favorites()->where('products.id', $product->id)->exists())->toBeFalse();
});

test('favourites are scoped per user', function () {
    $product = Product::factory()->create();

    $this->cashier->favorites()->attach($product->id);

    expect($this->cashier->favorites()->count())->toBe(1);
    expect($this->owner->favorites()->count())->toBe(0);
});

test('a product exposes the users who favourited it and casts the global flag', function () {
    $product = Product::factory()->create(['is_global_favorite' => 1]);

    $this->cashier->favorites()->attach($product->id);

    expect($product->is_global_favorite)->toBeTrue();
    expect($product->favoritedByUsers()->pluck('users.id')->all())->toBe([$this->cashier->id]);
});

test('starring a favourite requires the pos.operate permission', function () {
    $product = Product::factory()->create();

    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'staff')->first();
    $staff = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($staff, ['role' => 'staff', 'role_id' => $staffRole->id, 'is_active' => true]);

    $this->actingAs($staff)
        ->postJson(route('pos.favorites.toggle', $product))
        ->assertForbidden();
});

test('an admin can toggle a product as a global favourite', function () {
    $product = Product::factory()->create(['is_global_favorite' => false]);

    $this->actingAs($this->owner)
        ->patch(route('products.global-favorite', $product))
        ->assertRedirect();

    expect($product->fresh()->is_global_favorite)->toBeTrue();

    $this->actingAs($this->owner)
        ->patch(route('products.global-favorite', $product));

    expect($product->fresh()->is_global_favorite)->toBeFalse();
});

test('a cashier cannot toggle a global favourite', function () {
    $product = Product::factory()->create(['is_global_favorite' => false]);

    $this->actingAs($this->cashier)
        ->patch(route('products.global-favorite', $product))
        ->assertForbidden();

    expect($product->fresh()->is_global_favorite)->toBeFalse();
});

test('the favourites section merges tiers, dedupes, and sorts availability-first', function () {
    $salePoint = createSalePoint('Register');

    // Both a personal and a global favourite — must appear once, in the user tier.
    $bothAvailable = Product::factory()->create(['name' => 'Both Available', 'is_global_favorite' => true]);
    // Personal favourite, out of stock — user tier, sorted after available.
    $userUnavailable = Product::factory()->create(['name' => 'User Unavailable']);
    // Global favourites — the baseline tier, after all user favourites.
    $globalAvailable = Product::factory()->create(['name' => 'Global Available', 'is_global_favorite' => true]);
    $globalUnavailable = Product::factory()->create(['name' => 'Global Unavailable', 'is_global_favorite' => true]);

    $this->cashier->favorites()->attach([$bothAvailable->id, $userUnavailable->id]);

    $salePoint->addStock($bothAvailable, 5, 'manual_add', actor: $this->owner);
    $salePoint->addStock($globalAvailable, 5, 'manual_add', actor: $this->owner);

    app(OpenPosSessionAction::class)->handle($salePoint, 5000, $this->cashier);

    $this->actingAs($this->cashier)
        ->get(route('pos.index', ['storage_id' => $salePoint->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Session')
            ->has('favoriteProducts', 4)
            ->where('favoriteProducts.0.id', $bothAvailable->id)
            ->where('favoriteProducts.0.favorite_scope', 'user')
            ->where('favoriteProducts.0.is_favorite', true)
            ->where('favoriteProducts.1.id', $userUnavailable->id)
            ->where('favoriteProducts.1.favorite_scope', 'user')
            ->where('favoriteProducts.2.id', $globalAvailable->id)
            ->where('favoriteProducts.2.favorite_scope', 'global')
            ->where('favoriteProducts.2.is_favorite', false)
            ->where('favoriteProducts.3.id', $globalUnavailable->id)
            ->where('favoriteProducts.3.favorite_scope', 'global')
        );
});
