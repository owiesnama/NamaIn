<?php

use App\Actions\Pos\ClosePosSessionAction;
use App\Actions\Pos\OpenPosSessionAction;
use App\Actions\Treasury\ExecuteTreasuryTransferAction;
use App\Actions\Treasury\RecordTreasuryAdjustmentAction;
use App\Actions\Treasury\RecordTreasuryMovementAction;
use App\Enums\TreasuryMovementReason;
use App\Models\Role;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use App\Models\TreasuryTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $this->tenant);
    seedTenantRoles($this->tenant);

    $ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'owner')->first();
    $managerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'manager')->first();
    $cashierRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'cashier')->first();

    $this->owner = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->owner, ['role' => 'owner', 'role_id' => $ownerRole->id, 'is_active' => true]);

    $this->manager = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->manager, ['role' => 'manager', 'role_id' => $managerRole->id, 'is_active' => true]);

    $this->cashier = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->cashier, ['role' => 'cashier', 'role_id' => $cashierRole->id, 'is_active' => true]);
});

// ─────────────────────────────────────────────
// Balance calculation
// ─────────────────────────────────────────────

test('current balance equals opening balance when no movements exist', function () {
    $account = TreasuryAccount::factory()->withOpeningBalance(50000)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    expect($account->currentBalance())->toBe(50000);
});

test('current balance correctly sums all movements', function () {
    $account = TreasuryAccount::factory()->withOpeningBalance(10000)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    TreasuryMovement::create([
        'treasury_account_id' => $account->id,
        'created_by' => $this->owner->id,
        'movable_type' => TreasuryAccount::class,
        'movable_id' => $account->id,
        'reason' => TreasuryMovementReason::ManualAdjustment,
        'amount' => 5000,
        'balance_after' => 15000,
        'occurred_at' => now(),
    ]);

    TreasuryMovement::create([
        'treasury_account_id' => $account->id,
        'created_by' => $this->owner->id,
        'movable_type' => TreasuryAccount::class,
        'movable_id' => $account->id,
        'reason' => TreasuryMovementReason::ExpensePaid,
        'amount' => -3000,
        'balance_after' => 12000,
        'occurred_at' => now(),
    ]);

    expect($account->currentBalance())->toBe(12000);
});

// ─────────────────────────────────────────────
// RecordTreasuryMovementAction
// ─────────────────────────────────────────────

test('credits increase balance correctly', function () {
    $account = TreasuryAccount::factory()->withOpeningBalance(0)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $movement = app(RecordTreasuryMovementAction::class)->handle(
        account: $account,
        amount: 20000,
        reason: TreasuryMovementReason::PaymentReceived,
        movable: $account,
        actor: $this->owner,
    );

    expect($movement->amount)->toBe(20000);
    expect($movement->balance_after)->toBe(20000);
    expect($account->currentBalance())->toBe(20000);
});

test('debits decrease balance correctly', function () {
    $account = TreasuryAccount::factory()->withOpeningBalance(50000)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $movement = app(RecordTreasuryMovementAction::class)->handle(
        account: $account,
        amount: -15000,
        reason: TreasuryMovementReason::ExpensePaid,
        movable: $account,
        actor: $this->owner,
    );

    expect($movement->amount)->toBe(-15000);
    expect($movement->balance_after)->toBe(35000);
    expect($account->currentBalance())->toBe(35000);
});

test('debit below zero records a negative balance', function () {
    $account = TreasuryAccount::factory()->withOpeningBalance(5000)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $movement = app(RecordTreasuryMovementAction::class)->handle(
        account: $account,
        amount: -10000,
        reason: TreasuryMovementReason::ExpensePaid,
        movable: $account,
        actor: $this->owner,
    );

    expect($movement->balance_after)->toBe(-5000);
    expect($account->currentBalance())->toBe(-5000);
});

test('movement balance_after snapshot matches actual balance', function () {
    $account = TreasuryAccount::factory()->withOpeningBalance(1000)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $movement = app(RecordTreasuryMovementAction::class)->handle(
        account: $account,
        amount: 2500,
        reason: TreasuryMovementReason::PaymentReceived,
        movable: $account,
        actor: $this->owner,
    );

    expect($movement->balance_after)->toBe($account->currentBalance());
});

// ─────────────────────────────────────────────
// ExecuteTreasuryTransferAction
// ─────────────────────────────────────────────

test('transfer debits source and credits destination', function () {
    $from = TreasuryAccount::factory()->withOpeningBalance(100000)->create(['tenant_id' => $this->tenant->id]);
    $to = TreasuryAccount::factory()->withOpeningBalance(0)->create(['tenant_id' => $this->tenant->id]);

    app(ExecuteTreasuryTransferAction::class)->handle(
        from: $from,
        to: $to,
        amount: 30000,
        actor: $this->owner,
    );

    expect($from->currentBalance())->toBe(70000);
    expect($to->currentBalance())->toBe(30000);
});

test('transfer creates a TreasuryTransfer record', function () {
    $from = TreasuryAccount::factory()->withOpeningBalance(50000)->create(['tenant_id' => $this->tenant->id]);
    $to = TreasuryAccount::factory()->withOpeningBalance(0)->create(['tenant_id' => $this->tenant->id]);

    $transfer = app(ExecuteTreasuryTransferAction::class)->handle(
        from: $from,
        to: $to,
        amount: 10000,
        actor: $this->owner,
    );

    expect($transfer)->toBeInstanceOf(TreasuryTransfer::class);
    expect(TreasuryTransfer::count())->toBe(1);
});

test('same account transfer throws InvalidArgumentException', function () {
    $account = TreasuryAccount::factory()->withOpeningBalance(50000)->create(['tenant_id' => $this->tenant->id]);

    expect(fn () => app(ExecuteTreasuryTransferAction::class)->handle(
        from: $account,
        to: $account,
        amount: 10000,
        actor: $this->owner,
    ))->toThrow(InvalidArgumentException::class);
});

test('transfer with insufficient source balance allows negative and credits destination', function () {
    $from = TreasuryAccount::factory()->withOpeningBalance(5000)->create(['tenant_id' => $this->tenant->id]);
    $to = TreasuryAccount::factory()->withOpeningBalance(0)->create(['tenant_id' => $this->tenant->id]);

    app(ExecuteTreasuryTransferAction::class)->handle(
        from: $from,
        to: $to,
        amount: 10000,
        actor: $this->owner,
    );

    expect($from->currentBalance())->toBe(-5000);
    expect($to->currentBalance())->toBe(10000);
});

// ─────────────────────────────────────────────
// RecordTreasuryAdjustmentAction
// ─────────────────────────────────────────────

test('upward adjustment records positive delta', function () {
    $account = TreasuryAccount::factory()->withOpeningBalance(10000)->create(['tenant_id' => $this->tenant->id]);

    app(RecordTreasuryAdjustmentAction::class)->handle(
        account: $account,
        newBalance: 15000,
        notes: 'Found extra cash',
        actor: $this->owner,
    );

    expect($account->currentBalance())->toBe(15000);
    expect(TreasuryMovement::first()->amount)->toBe(5000);
});

test('downward adjustment records negative delta', function () {
    $account = TreasuryAccount::factory()->withOpeningBalance(20000)->create(['tenant_id' => $this->tenant->id]);

    app(RecordTreasuryAdjustmentAction::class)->handle(
        account: $account,
        newBalance: 18000,
        notes: 'Cash count short',
        actor: $this->owner,
    );

    expect($account->currentBalance())->toBe(18000);
    expect(TreasuryMovement::first()->amount)->toBe(-2000);
});

test('balance after adjustment equals the specified new balance', function () {
    $account = TreasuryAccount::factory()->withOpeningBalance(10000)->create(['tenant_id' => $this->tenant->id]);

    $movement = app(RecordTreasuryAdjustmentAction::class)->handle(
        account: $account,
        newBalance: 12500,
        notes: 'Reconciliation',
        actor: $this->owner,
    );

    expect($movement->balance_after)->toBe(12500);
});

// ─────────────────────────────────────────────
// POS session integration
// ─────────────────────────────────────────────

test('opening a POS session credits the cash drawer if one exists', function () {
    $storage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);

    $cashDrawer = TreasuryAccount::factory()->cash()->withOpeningBalance(0)->create([
        'tenant_id' => $this->tenant->id,
        'sale_point_id' => $storage->id,
    ]);

    app(OpenPosSessionAction::class)->execute($storage, 10000, $this->cashier);

    expect($cashDrawer->currentBalance())->toBe(10000);
    expect(TreasuryMovement::first()->reason)->toBe(TreasuryMovementReason::PosOpeningFloat);
});

test('opening a POS session with zero float does not record a movement', function () {
    $storage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);

    TreasuryAccount::factory()->cash()->withOpeningBalance(0)->create([
        'tenant_id' => $this->tenant->id,
        'sale_point_id' => $storage->id,
    ]);

    app(OpenPosSessionAction::class)->execute($storage, 0, $this->cashier);

    expect(TreasuryMovement::count())->toBe(0);
});

test('closing a POS session with variance records an adjustment', function () {
    $storage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);

    $cashDrawer = TreasuryAccount::factory()->cash()->withOpeningBalance(0)->create([
        'tenant_id' => $this->tenant->id,
        'sale_point_id' => $storage->id,
    ]);

    $session = app(OpenPosSessionAction::class)->execute($storage, 10000, $this->cashier);

    // Cashier counted 9500 but system expects 10000
    app(ClosePosSessionAction::class)->execute($session, 9500, $this->owner);

    expect($cashDrawer->currentBalance())->toBe(9500);

    $adjustment = TreasuryMovement::where('reason', TreasuryMovementReason::ManualAdjustment)->first();
    expect($adjustment)->not->toBeNull();
    expect($adjustment->amount)->toBe(-500);
});

test('closing a POS session with matching float records no adjustment', function () {
    $storage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);

    TreasuryAccount::factory()->cash()->withOpeningBalance(0)->create([
        'tenant_id' => $this->tenant->id,
        'sale_point_id' => $storage->id,
    ]);

    $session = app(OpenPosSessionAction::class)->execute($storage, 10000, $this->cashier);

    app(ClosePosSessionAction::class)->execute($session, 10000, $this->owner);

    // Only the opening float movement, no adjustment
    expect(TreasuryMovement::where('reason', TreasuryMovementReason::ManualAdjustment)->count())->toBe(0);
});

// ─────────────────────────────────────────────
// Policy enforcement
// ─────────────────────────────────────────────

test('owner can view treasury accounts', function () {
    expect($this->owner->can('viewAny', TreasuryAccount::class))->toBeTrue();
    expect($this->owner->can('view', TreasuryAccount::class))->toBeTrue();
});

test('manager can view and create treasury accounts', function () {
    expect($this->manager->can('viewAny', TreasuryAccount::class))->toBeTrue();
    expect($this->manager->can('create', TreasuryAccount::class))->toBeTrue();
    expect($this->manager->can('transfer', TreasuryAccount::class))->toBeTrue();
    expect($this->manager->can('adjust', TreasuryAccount::class))->toBeTrue();
});

test('cashier cannot view or manage treasury accounts', function () {
    expect($this->cashier->can('viewAny', TreasuryAccount::class))->toBeFalse();
    expect($this->cashier->can('create', TreasuryAccount::class))->toBeFalse();
    expect($this->cashier->can('transfer', TreasuryAccount::class))->toBeFalse();
    expect($this->cashier->can('adjust', TreasuryAccount::class))->toBeFalse();
});
