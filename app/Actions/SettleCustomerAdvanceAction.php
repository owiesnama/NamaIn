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
        $invoice->recordPayment(
            amount: $amount,
            method: PaymentMethod::Advance,
            notes: $notes ?? "ADV-{$advance->id}",
        );

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
