<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TreasuryTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app('currentTenant')->id;

        return [
            'from_account_id' => ['required', Rule::exists('treasury_accounts', 'id')->where('tenant_id', $tenantId)],
            'to_account_id' => ['required', Rule::exists('treasury_accounts', 'id')->where('tenant_id', $tenantId), 'different:from_account_id'],
            'amount' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
