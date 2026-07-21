<?php

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PosSession;
use App\Models\Preference;
use App\Models\Storage;
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

    $this->bank = TreasuryAccount::factory()->bank()->create(['tenant_id' => $this->tenant->id]);
    Preference::create(['tenant_id' => $this->tenant->id, 'key' => 'pos_default_bank_account_id', 'value' => $this->bank->id]);

    $storage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);
    $session = PosSession::factory()->create(['tenant_id' => $this->tenant->id, 'storage_id' => $storage->id, 'opened_by' => $this->user->id]);
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    // Corrupted historical state: a POS bank-transfer payment with no movement.
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pos_session_id' => $session->id,
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
        'payment_method' => PaymentMethod::BankTransfer,
        'total' => 2000,
    ]);

    $this->payment = Payment::create([
        'tenant_id' => $this->tenant->id,
        'invoice_id' => $invoice->id,
        'amount' => 2000,
        'payment_method' => PaymentMethod::BankTransfer,
        'direction' => PaymentDirection::In,
        'paid_at' => now(),
        'created_by' => $this->user->id,
    ]);
});

function runBackfill(): void
{
    (require database_path('migrations/2026_07_21_011010_backfill_pos_bank_transfer_treasury_movements.php'))->up();
}

test('it backfills the missing treasury movement to the default bank account', function () {
    expect($this->payment->treasuryMovements()->count())->toBe(0);

    runBackfill();

    $this->payment->refresh();
    expect($this->payment->treasury_account_id)->toBe($this->bank->id)
        ->and($this->payment->treasuryMovements()->count())->toBe(1);
    expect((int) $this->bank->fresh()->currentBalance())->toBe(200000);
});

test('the backfill is idempotent', function () {
    runBackfill();
    runBackfill();

    expect($this->payment->treasuryMovements()->count())->toBe(1);
    expect((int) $this->bank->fresh()->currentBalance())->toBe(200000);
});
