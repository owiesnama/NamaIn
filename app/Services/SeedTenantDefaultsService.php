<?php

namespace App\Services;

use App\Enums\StorageType;
use App\Enums\TreasuryAccountType;
use App\Models\Customer;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\TreasuryAccount;

class SeedTenantDefaultsService
{
    public function seedForTenant(Tenant $tenant): void
    {
        Storage::create([
            'name' => __('Main Warehouse'),
            'address' => '—',
            'type' => StorageType::WAREHOUSE,
            'tenant_id' => $tenant->id,
        ]);

        $pos = Storage::create([
            'name' => __('Point of Sale'),
            'address' => '—',
            'type' => StorageType::SALE_POINT,
            'tenant_id' => $tenant->id,
        ]);

        TreasuryAccount::create([
            'name' => __('Cash Register'),
            'type' => TreasuryAccountType::Cash,
            'sale_point_id' => $pos->id,
            'opening_balance' => 0,
            'currency' => 'SDG',
            'tenant_id' => $tenant->id,
        ]);

        TreasuryAccount::create([
            'name' => __('Main Cash'),
            'type' => TreasuryAccountType::Cash,
            'opening_balance' => 0,
            'currency' => 'SDG',
            'tenant_id' => $tenant->id,
        ]);

        TreasuryAccount::create([
            'name' => __('Cheque Clearing'),
            'type' => TreasuryAccountType::ChequeClearing,
            'opening_balance' => 0,
            'currency' => 'SDG',
            'tenant_id' => $tenant->id,
        ]);

        Customer::create([
            'name' => __('Walk-in Customer'),
            'address' => 'N/A',
            'phone_number' => 'N/A',
            'is_system' => true,
            'tenant_id' => $tenant->id,
        ]);
    }
}
