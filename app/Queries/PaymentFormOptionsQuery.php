<?php

namespace App\Queries;

use App\Enums\PaymentMethod;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\TreasuryAccount;

class PaymentFormOptionsQuery
{
    public function forCreate(): array
    {
        return [
            'customers' => Customer::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'banks' => Bank::orderBy('name')->get(),
            'payment_methods' => PaymentMethod::casesWithLabels(),
            'treasury_accounts' => TreasuryAccount::active()->get()->map(fn (TreasuryAccount $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
                'type_label' => $account->type->label(),
                'current_balance' => $account->currentBalance(),
            ]),
        ];
    }
}
