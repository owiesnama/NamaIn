<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TreasuryTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_account_id' => 'required|exists:treasury_accounts,id',
            'to_account_id' => 'required|exists:treasury_accounts,id|different:from_account_id',
            'amount' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
