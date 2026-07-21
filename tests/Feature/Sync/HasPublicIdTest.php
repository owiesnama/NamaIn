<?php

use App\Enums\StorageType;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function assertLooksLikeUlid(?string $value): void
{
    expect($value)->not->toBeNull();
    expect(strlen((string) $value))->toBe(26);
    expect($value)->toBe(strtolower((string) $value));
    expect($value)->toMatch('/^[0-9a-hjkmnp-tv-z]{26}$/');
}

it('assigns a lowercase ulid public_id to new base models', function () {
    $product = Product::create(['name' => 'Widget', 'cost' => 10]);

    assertLooksLikeUlid($product->public_id);
});

it('assigns unique public_ids across rows', function () {
    $a = Product::create(['name' => 'A', 'cost' => 1]);
    $b = Product::create(['name' => 'B', 'cost' => 1]);

    expect($a->public_id)->not->toBe($b->public_id);
});

it('assigns public_id to users, roles, permissions and tenants', function () {
    $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'is_active' => true]);
    $user = User::factory()->create();
    $permission = Permission::create(['slug' => 'things.do-'.uniqid(), 'group' => 'things', 'description' => 'Do things']);
    $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Boss', 'slug' => 'boss-'.uniqid()]);

    assertLooksLikeUlid($tenant->public_id);
    assertLooksLikeUlid($user->public_id);
    assertLooksLikeUlid($permission->public_id);
    assertLooksLikeUlid($role->public_id);
});

it('mints a public_id on the raw stocks insert in Storage::addStock', function () {
    $storage = Storage::create([
        'name' => 'Main',
        'address' => 'x',
        'type' => StorageType::WAREHOUSE,
    ]);
    $product = Product::create(['name' => 'Bolt', 'cost' => 2]);

    $storage->addStock($product, 5, 'test');

    $publicId = DB::table('stocks')
        ->where('storage_id', $storage->id)
        ->where('product_id', $product->id)
        ->value('public_id');

    assertLooksLikeUlid($publicId);
});
