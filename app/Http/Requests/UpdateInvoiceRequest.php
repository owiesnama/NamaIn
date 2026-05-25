<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app('currentTenant')->id;
        $invocableTable = $this->input('invocable.type') === Customer::class ? 'customers' : 'suppliers';

        return [
            'total' => 'required|numeric|min:0',
            'invocable' => 'required|array',
            'invocable.id' => [
                'required',
                'integer',
                Rule::exists($invocableTable, 'id')->where('tenant_id', $tenantId),
            ],
            'invocable.name' => 'required|string',
            'invocable.type' => ['required', 'string', Rule::in([Customer::class, Supplier::class])],
            'products' => 'required|array|min:1',
            'products.*.product' => [
                'integer',
                'required',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'products.*.quantity' => 'numeric|required|gt:0',
            'products.*.unit' => 'integer|required|exists:units,id',
            'products.*.price' => 'numeric|required|min:0',
            'products.*.description' => 'nullable|string',
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'discount' => 'nullable|numeric|min:0',
        ];
    }
}
