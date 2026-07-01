<?php

use App\Models\Tenant;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use App\Queries\Reports\TreasuryReconciliationQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the reconciliation report only lists movements of the current tenant', function () {
    $ownAccount = TreasuryAccount::factory()->create(['tenant_id' => app('currentTenant')->id]);
    TreasuryMovement::factory()->create([
        'treasury_account_id' => $ownAccount->id,
        'movable_type' => TreasuryAccount::class,
        'movable_id' => $ownAccount->id,
        'amount' => 500,
        'balance_after' => 500,
        'occurred_at' => now(),
    ]);

    $otherTenant = Tenant::factory()->create();
    $foreignAccount = TreasuryAccount::factory()->create(['tenant_id' => $otherTenant->id]);
    TreasuryMovement::factory()->create([
        'tenant_id' => $otherTenant->id,
        'treasury_account_id' => $foreignAccount->id,
        'movable_type' => TreasuryAccount::class,
        'movable_id' => $foreignAccount->id,
        'amount' => 999,
        'balance_after' => 999,
        'occurred_at' => now(),
    ]);

    $rows = app(TreasuryReconciliationQuery::class)->get(now()->subDay(), now()->addDay());

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['account_name'])->toBe($ownAccount->name);

    // The tenant scope now applies to treasury movements directly.
    expect(TreasuryMovement::count())->toBe(1)
        ->and(TreasuryMovement::withoutGlobalScopes()->count())->toBe(2);
});
