<?php

// Seed the offline SQLite runtime through the app's real onboarding path.

use App\Actions\Pos\OpenPosSessionAction;
use App\Actions\ProvisionTenantAction;
use App\Enums\StorageType;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'offline@namain.test')->first();
if (! $user) {
    $user = User::create([
        'name' => 'Offline Cashier',
        'email' => 'offline@namain.test',
        'password' => Hash::make('offline-pass-1'),
        'email_verified_at' => now(),
    ]);
}

$tenant = Tenant::where('slug', 'nama')->first()
    ?? app(ProvisionTenantAction::class)->handle('Nama Offline', 'nama', $user);

app()->instance('currentTenant', $tenant);
auth()->login($user);

$salePoint = Storage::where('tenant_id', $tenant->id)
    ->where('type', StorageType::SALE_POINT)->firstOrFail();

$product = Product::where('tenant_id', $tenant->id)->where('name', 'Offline Cola')->first()
    ?? Product::create(['name' => 'Offline Cola', 'cost' => 3, 'price' => 5, 'tenant_id' => $tenant->id]);

$salePoint->addStock($product, 100, 'adjustment', null, $user);

if (! $salePoint->refresh()->active_session_id) {
    app(OpenPosSessionAction::class)->handle($salePoint, 0, $user);
}

echo json_encode([
    'tenant' => $tenant->slug,
    'r0' => \DB::table('registers')->where('tenant_id', $tenant->id)->value('code'),
    'session_id' => $salePoint->refresh()->active_session_id,
    'product_id' => $product->id,
    'stock' => $salePoint->quantityOf($product),
]), "\n";
