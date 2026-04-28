<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
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
        return [
            'session_id' => 'required|exists:pos_sessions,id',
            'customer_id' => $this->isCredit() ? 'required|exists:customers,id' : 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.unit_id' => 'nullable|exists:units,id',
            'total' => 'required|numeric',
            'payment_method' => ['nullable', new Enum(PaymentMethod::class)],
            'idempotency_key' => 'nullable|string',
            'acknowledge_transfers' => 'nullable|boolean',
        ];
    }

    private function isCredit(): bool
    {
        return $this->input('payment_method') === PaymentMethod::Credit->value;
    }
}
