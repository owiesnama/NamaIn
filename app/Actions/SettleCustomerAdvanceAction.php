<?php

namespace App\Actions;

use App\Actions\Treasury\RecordTreasuryMovementAction;
use App\Enums\PaymentMethod;
use App\Enums\TreasuryMovementReason;
use App\Exceptions\OverSettlementException;
use App\Models\CustomerAdvance;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TreasuryAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SettleCustomerAdvanceAction
{
    public function __construct(
        private RecordTreasuryMovementAction $recordMovement,
    ) {}

    /**
     * Settle a customer advance — either via direct repayment or invoice offset.
     *
     * Scenario 1 / 3 (direct repayment): $invoice = null
     *   - Creates a payment linked to the advance.
     *   - Records a treasury credit movement (CustomerAdvanceRepaid).
     *
     * Scenario 2 (invoice offset): $invoice provided
     *   - Records a payment against the invoice (method = Advance, no treasury movement).
     *   - Creates an advance payment for audit trail.
     *   - No money enters/leaves the treasury — cash already left when advance was given.
     */
    public function handle(
        CustomerAdvance $advance,
        float $amount,
        TreasuryAccount $treasury,
        User $actor,
        ?Invoice $invoice = null,
        ?string $notes = null,
    ): Payment {
        if ($amount > $advance->remainingBalance()) {
            throw new OverSettlementException($advance, $amount);
        }

        return DB::transaction(function () use ($advance, $amount, $treasury, $actor, $invoice, $notes) {
            if ($invoice === null) {
                return $this->settleDirectly($advance, $amount, $treasury, $actor, $notes);
            }

            return $this->settleViaInvoice($advance, $amount, $treasury, $actor, $invoice, $notes);
        });
    }

    private function settleDirectly(
        CustomerAdvance $advance,
        float $amount,
        TreasuryAccount $treasury,
        User $actor,
        ?string $notes,
    ): Payment {
        $payment = Payment::create([
            'payable_type' => CustomerAdvance::class,
            'payable_id' => $advance->id,
            'treasury_account_id' => $treasury->id,
            'amount' => $amount,
            'payment_method' => PaymentMethod::Cash,
            'paid_at' => now(),
            'created_by' => $actor->id,
            'notes' => $notes,
        ]);

        $this->recordMovement->handle(
            account: $treasury,
            amount: (int) round($amount * 100),
            reason: TreasuryMovementReason::CustomerAdvanceRepaid,
            movable: $payment,
            actor: $actor,
        );

        $advance->updateSettlementStatus();

        return $payment;
    }

    private function settleViaInvoice(
        CustomerAdvance $advance,
        float $amount,
        TreasuryAccount $treasury,
        User $actor,
        Invoice $invoice,
        ?string $notes,
    ): Payment {
        // Record the invoice payment (no treasury movement — cash is already out)
        $invoice->recordPayment(
            amount: $amount,
            method: PaymentMethod::Advance,
            notes: $notes ?? "ADV-{$advance->id}",
        );

        // Create an advance payment record for audit trail
        $advancePayment = Payment::create([
            'payable_type' => CustomerAdvance::class,
            'payable_id' => $advance->id,
            'treasury_account_id' => $treasury->id,
            'amount' => $amount,
            'payment_method' => PaymentMethod::Advance,
            'paid_at' => now(),
            'created_by' => $actor->id,
            'notes' => $notes ?? "Invoice offset for #{$invoice->id}",
        ]);

        $advance->updateSettlementStatus();

        return $advancePayment;
    }
}
