<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'products.*.product' => 'integer|required',
            'products.*.quantity' => 'integer|required',
            'products.*.unit' => 'integer|required',
            'products.*.price' => 'integer|required',
        ];
    }
}
