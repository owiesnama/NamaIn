<?php

namespace App\Http\Requests;

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_id' => 'required_without:payable_id|nullable|exists:invoices,id',
            'payable_id' => 'required_without:invoice_id|nullable|integer',
            'payable_type' => ['required_with:payable_id', 'nullable', 'string', Rule::in([Customer::class, Supplier::class])],
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'direction' => ['required', Rule::enum(PaymentDirection::class)],
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'paid_at' => 'nullable|date',

            // Bank Transfer fields
            'bank_name' => 'required_if:payment_method,bank_transfer|nullable|string|max:255',
            'receipt' => 'nullable|string|max:255',

            // Cheque fields
            'cheque_bank_id' => 'required_if:payment_method,cheque|nullable',
            'cheque_due_date' => 'required_if:payment_method,cheque|nullable|date',
            'cheque_number' => 'required_if:payment_method,cheque|nullable|string|max:255',

            // Treasury
            'treasury_account_id' => 'nullable|exists:treasury_accounts,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_id.required_without' => 'Please select an invoice or a customer/supplier',
            'payable_id.required_without' => 'Please select an invoice or a customer/supplier',
            'invoice_id.exists' => 'The selected invoice does not exist',
            'amount.required' => 'Payment amount is required',
            'amount.min' => 'Payment amount must be greater than zero',
            'payment_method.required' => 'Please select a payment method',
        ];
    }
}
