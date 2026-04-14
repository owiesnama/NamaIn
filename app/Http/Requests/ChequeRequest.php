<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ChequeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'payee_id' => 'required|integer',
            'payee_type' => 'required|string',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'type' => 'required|in:1,0',
            'due' => 'required|date',
            'bank_id' => 'required|exists:banks,id',
            'reference_number' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ];
    }
}
