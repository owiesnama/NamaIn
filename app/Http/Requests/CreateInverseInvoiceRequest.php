<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateInverseInvoiceRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_invoice_id' => 'required|exists:invoices,id',
            'inverse_reason' => 'required|string|max:1000',
            'products' => 'required|array|min:1',
            'products.*.transaction_id' => [
                'required',
                'exists:transactions,id',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $transaction = Transaction::find($value);
                    $quantity = $this->input("products.{$index}.quantity");

                    if ($transaction && $quantity > $transaction->quantity) {
                        $fail("The quantity for product {$transaction->product->name} cannot exceed the original quantity of {$transaction->quantity}.");
                    }
                },
            ],
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.unit_id' => 'nullable|exists:units,id',
            'products.*.price' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'refund_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string',
        ];
    }
}
