<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $tenantId = app('currentTenant')->id;
        $invocableTable = $this->input('invocable.type') === 'App\Models\Customer' ? 'customers' : 'suppliers';

        return [
            'total' => 'required|numeric|min:0',
            'invocable' => 'required|array',
            'invocable.id' => [
                'required',
                'integer',
                Rule::exists($invocableTable, 'id')->where('tenant_id', $tenantId),
            ],
            'invocable.name' => 'required|string',
            'invocable.type' => 'required|string|in:App\Models\Customer,App\Models\Supplier',
            'products' => 'required|array|min:1',
            'products.*.product' => [
                'integer',
                'required',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'products.*.quantity' => 'numeric|required|gt:0',
            'products.*.unit' => 'integer|required|exists:units,id',
            'products.*.price' => 'numeric|required|min:0',
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'discount' => 'nullable|numeric|min:0',
            'initial_payment_amount' => 'nullable|numeric|min:0',
            'payment_reference' => 'nullable|string|max:255',
            'payment_notes' => 'nullable|string|max:1000',
            'receipt' => 'nullable|string|max:255',
            'treasury_account_id' => [
                'nullable',
                Rule::exists('treasury_accounts', 'id')->where('tenant_id', $tenantId),
            ],
            'bank_name' => 'nullable|string|max:255',
            'cheque_due_date' => 'nullable|date',
            'cheque_bank_id' => 'nullable|integer',
            'cheque_number' => 'nullable|string|max:255',
        ];
    }
}
