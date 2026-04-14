<?php

namespace App\Actions;

use App\Enums\ChequeStatus;
use App\Models\Cheque;
use Illuminate\Database\Eloquent\Model;

class CreateChequeAction
{
    public function execute(Model $payee, array $data): Cheque
    {
        return Cheque::create([
            'chequeable_id' => $payee->id,
            'chequeable_type' => get_class($payee),
            'amount' => $data['amount'],
            'type' => $data['type'], // 1 for Receivable (Customer), 0 for Payable (Supplier)
            'due' => $data['due'],
            'bank_id' => $data['bank_id'],
            'reference_number' => $data['reference_number'],
            'status' => ChequeStatus::Drafted,
            'notes' => $data['notes'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
        ]);
    }
}
