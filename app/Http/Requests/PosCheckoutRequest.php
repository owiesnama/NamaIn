<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class PosCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('pos.operate');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = app('currentTenant')->id;

        return [
            'session_id' => ['required', Rule::exists('pos_sessions', 'id')->where('tenant_id', $tenantId)],
            'customer_id' => $this->isCredit()
                ? ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)]
                : ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.unit_id' => ['nullable', Rule::exists('units', 'id')->where('tenant_id', $tenantId)],
            'total' => 'required|numeric',
            'payment_method' => ['nullable', new Enum(PaymentMethod::class)],
            'treasury_account_id' => [
                'nullable',
                Rule::exists('treasury_accounts', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true),
            ],
            'customer_type' => ['nullable', Rule::in([Customer::class])],
            'idempotency_key' => 'nullable|string',
            'acknowledge_transfers' => 'nullable|boolean',
        ];
    }

    private function isCredit(): bool
    {
        return $this->input('payment_method') === PaymentMethod::Credit->value;
    }
}
