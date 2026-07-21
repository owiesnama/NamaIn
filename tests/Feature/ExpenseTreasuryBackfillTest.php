<?php

use App\Models\Expense;
use App\Models\Tenant;
use App\Models\TreasuryAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $this->tenant);

    $this->user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->user, ['role' => 'owner', 'is_active' => true]);

    $this->cash = TreasuryAccount::factory()->cash()->create(['tenant_id' => $this->tenant->id]);

    // Corrupted historical state: an expense with no treasury account or movement.
    $this->expense = Expense::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'amount' => 500,
        'treasury_account_id' => null,
        'expensed_at' => now(),
    ]);
});

function runExpenseBackfill(): void
{
    (require database_path('migrations/2026_07_21_011625_backfill_expense_treasury_movements.php'))->up();
}

test('it backfills the missing negative movement to the default cash account', function () {
    expect($this->expense->treasuryMovements()->count())->toBe(0);

    runExpenseBackfill();

    $this->expense->refresh();
    expect($this->expense->treasury_account_id)->toBe($this->cash->id)
        ->and($this->expense->treasuryMovements()->count())->toBe(1);
    expect((int) $this->cash->fresh()->currentBalance())->toBe(-50000);
});

test('the expense backfill is idempotent', function () {
    runExpenseBackfill();
    runExpenseBackfill();

    expect($this->expense->treasuryMovements()->count())->toBe(1);
    expect((int) $this->cash->fresh()->currentBalance())->toBe(-50000);
});
