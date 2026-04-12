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
        return [
            'total' => 'required|integer',
            'invocable' => 'required|array',
            'invocable.id' => 'required|integer|',
            'invocable.name' => 'required|string',
            'products.*.product' => 'integer|required',
            'products.*.quantity' => 'integer|required',
            'products.*.unit' => 'integer|required',
            'products.*.price' => 'integer|required',
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'discount' => 'nullable|numeric|min:0',
            'initial_payment_amount' => 'nullable|numeric|min:0',
            'payment_reference' => 'nullable|string|max:255',
            'payment_notes' => 'nullable|string|max:1000',
        ];
    }
}
