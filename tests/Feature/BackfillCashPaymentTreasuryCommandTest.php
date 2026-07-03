<?php

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\TreasuryMovementReason;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = app('currentTenant');

    $this->orphanedCashPayment = fn (array $attributes = []) => Payment::factory()->create(array_merge([
        'invoice_id' => null,
        'payment_method' => PaymentMethod::Cash,
        'treasury_account_id' => null,
        'direction' => PaymentDirection::In,
        'amount' => 150.5,
        'paid_at' => Carbon::parse('2026-06-01 10:00:00'),
    ], $attributes));
});

test('it attaches orphaned incoming cash payments to the default cash account and records a credit', function () {
    $account = TreasuryAccount::factory()->cash()->create(['tenant_id' => $this->tenant->id]);
    $payment = ($this->orphanedCashPayment)();

    $this->artisan('treasury:backfill-cash-payments')
        ->expectsOutputToContain('Backfilled 1 cash payment(s); skipped 0.')
        ->assertExitCode(0);

    expect($payment->refresh()->treasury_account_id)->toBe($account->id);

    $movement = TreasuryMovement::withoutGlobalScopes()->sole();

    expect($movement->treasury_account_id)->toBe($account->id)
        ->and($movement->amount)->toBe(15050)
        ->and($movement->reason)->toBe(TreasuryMovementReason::PaymentReceived)
        ->and($movement->movable_type)->toBe(Payment::class)
        ->and($movement->movable_id)->toBe($payment->id)
        ->and($movement->occurred_at->toDateTimeString())->toBe('2026-06-01 10:00:00');
});

test('it records outgoing cash payments as debits with the expense paid reason', function () {
    TreasuryAccount::factory()->cash()->create(['tenant_id' => $this->tenant->id]);
    ($this->orphanedCashPayment)(['direction' => PaymentDirection::Out]);

    $this->artisan('treasury:backfill-cash-payments')->assertExitCode(0);

    $movement = TreasuryMovement::withoutGlobalScopes()->sole();

    expect($movement->amount)->toBe(-15050)
        ->and($movement->reason)->toBe(TreasuryMovementReason::ExpensePaid);
});

test('it ignores payments that are not orphaned cash payments', function () {
    $account = TreasuryAccount::factory()->cash()->create(['tenant_id' => $this->tenant->id]);

    ($this->orphanedCashPayment)(['payment_method' => PaymentMethod::BankTransfer]);
    ($this->orphanedCashPayment)(['treasury_account_id' => $account->id]);

    $alreadyRecorded = ($this->orphanedCashPayment)();
    TreasuryMovement::create([
        'treasury_account_id' => $account->id,
        'created_by' => $alreadyRecorded->created_by,
        'movable_type' => Payment::class,
        'movable_id' => $alreadyRecorded->id,
        'reason' => TreasuryMovementReason::PaymentReceived,
        'amount' => 15050,
        'balance_after' => 15050,
        'occurred_at' => now(),
    ]);

    $this->artisan('treasury:backfill-cash-payments')
        ->expectsOutputToContain('Backfilled 0 cash payment(s); skipped 0.')
        ->assertExitCode(0);

    expect(TreasuryMovement::withoutGlobalScopes()->count())->toBe(1)
        ->and($alreadyRecorded->refresh()->treasury_account_id)->toBeNull();
});

test('it warns and skips tenants without an active shared cash account', function () {
    TreasuryAccount::factory()->cash()->inactive()->create(['tenant_id' => $this->tenant->id]);
    $payment = ($this->orphanedCashPayment)();

    $this->artisan('treasury:backfill-cash-payments')
        ->expectsOutputToContain("Tenant [{$this->tenant->slug}]: 1 orphaned cash payment(s) but no active shared cash account — skipped.")
        ->expectsOutputToContain('Backfilled 0 cash payment(s); skipped 1.')
        ->assertExitCode(0);

    expect($payment->refresh()->treasury_account_id)->toBeNull()
        ->and(TreasuryMovement::withoutGlobalScopes()->count())->toBe(0);
});

test('the dry run reports what would change without writing', function () {
    $account = TreasuryAccount::factory()->cash()->create(['tenant_id' => $this->tenant->id]);
    $payment = ($this->orphanedCashPayment)();

    $this->artisan('treasury:backfill-cash-payments --dry-run')
        ->expectsOutputToContain("Would attach payment #{$payment->id}")
        ->expectsOutputToContain('Would backfill 1 cash payment(s); skipped 0.')
        ->assertExitCode(0);

    expect($payment->refresh()->treasury_account_id)->toBeNull()
        ->and(TreasuryMovement::withoutGlobalScopes()->count())->toBe(0);
});

test('it backfills each tenant into its own default cash account', function () {
    $account = TreasuryAccount::factory()->cash()->create(['tenant_id' => $this->tenant->id]);
    $payment = ($this->orphanedCashPayment)();

    $otherTenant = Tenant::create(['name' => 'Other Org', 'slug' => 'other-org', 'is_active' => true]);
    $otherAccount = TreasuryAccount::factory()->cash()->create(['tenant_id' => $otherTenant->id]);
    $otherPayment = ($this->orphanedCashPayment)(['tenant_id' => $otherTenant->id]);

    $this->artisan('treasury:backfill-cash-payments')
        ->expectsOutputToContain('Backfilled 2 cash payment(s); skipped 0.')
        ->assertExitCode(0);

    expect($payment->refresh()->treasury_account_id)->toBe($account->id)
        ->and($otherPayment->refresh()->treasury_account_id)->toBe($otherAccount->id);
});
