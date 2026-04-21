<?php

namespace App\Actions;

use App\Enums\ChequeStatus;
use App\Models\Bank;
use App\Models\Cheque;
use Illuminate\Database\Eloquent\Model;

class CreateChequeAction
{
    public function handle(Model $payee, array $data): Cheque
    {
        return Cheque::create([
            'chequeable_id' => $payee->id,
            'chequeable_type' => get_class($payee),
            'amount' => $data['amount'],
            'type' => $data['type'],
            'due' => $data['due'],
            'bank_id' => $this->resolveBankId($data['bank_id'] ?? null),
            'reference_number' => $data['reference_number'],
            'status' => ChequeStatus::Drafted,
            'notes' => $data['notes'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
        ]);
    }

    private function resolveBankId(mixed $bankId): ?int
    {
        if (! $bankId) {
            return null;
        }

        if (is_numeric($bankId)) {
            return (int) $bankId;
        }

        return Bank::firstOrCreate(['name' => $bankId])->id;
    }
}
