<?php

namespace App\Actions;

use App\Actions\Treasury\RecordTreasuryMovementAction;
use App\Enums\CustomerAdvanceStatus;
use App\Enums\TreasuryMovementReason;
use App\Models\Customer;
use App\Models\CustomerAdvance;
use App\Models\TreasuryAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordCustomerAdvanceAction
{
    public function __construct(
        private RecordTreasuryMovementAction $recordMovement,
    ) {}

    /**
     * Record a new customer advance, deducting the amount from the treasury.
     */
    public function handle(
        Customer $customer,
        float $amount,
        TreasuryAccount $treasury,
        User $actor,
        ?string $notes = null,
        ?\DateTimeInterface $givenAt = null,
    ): CustomerAdvance {
        return DB::transaction(function () use ($customer, $amount, $treasury, $actor, $notes, $givenAt) {
            $advance = CustomerAdvance::create([
                'customer_id' => $customer->id,
                'treasury_account_id' => $treasury->id,
                'created_by' => $actor->id,
                'amount' => $amount,
                'settled_amount' => 0,
                'status' => CustomerAdvanceStatus::Outstanding,
                'notes' => $notes,
                'given_at' => $givenAt ?? now(),
            ]);

            $this->recordMovement->handle(
                account: $treasury,
                amount: -(int) round($amount * 100),
                reason: TreasuryMovementReason::CustomerAdvanceGiven,
                movable: $advance,
                actor: $actor,
            );

            return $advance;
        });
    }
}
