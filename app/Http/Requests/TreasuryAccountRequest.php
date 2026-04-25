<?php

namespace App\Http\Requests;

use App\Enums\TreasuryAccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TreasuryAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            return [
                'name' => 'required|string|max:255',
                'bank_id' => 'nullable|exists:banks,id',
                'notes' => 'nullable|string|max:1000',
            ];
        }

        return [
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::enum(TreasuryAccountType::class)],
            'opening_balance' => 'required|integer|min:0',
            'currency' => 'required|string|size:3',
            'sale_point_id' => 'nullable|exists:storages,id',
            'bank_id' => 'nullable|exists:banks,id',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
