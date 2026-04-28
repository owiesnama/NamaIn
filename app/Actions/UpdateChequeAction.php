<?php

namespace App\Actions;

use App\Models\Cheque;

class UpdateChequeAction
{
    public function __construct(
        private ResolveChequeBankAction $resolveChequeBank,
    ) {}

    public function handle(Cheque $cheque, array $attributes): Cheque
    {
        $cheque->update([
            'chequeable_id' => $attributes['payee_id'],
            'chequeable_type' => $attributes['payee_type'],
            'invoice_id' => $attributes['invoice_id'] ?? null,
            'type' => $attributes['type'],
            'amount' => $attributes['amount'],
            'bank_id' => $this->resolveChequeBank->handle($attributes['bank_id']),
            'due' => $attributes['due'],
            'reference_number' => $attributes['reference_number'],
            'notes' => $attributes['notes'] ?? null,
        ]);

        return $cheque;
    }
}
