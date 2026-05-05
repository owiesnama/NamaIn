<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuickUpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string',
            'cost' => 'sometimes|required|numeric|gt:0',
            'price' => 'sometimes|nullable|numeric|min:0',
            'currency' => 'sometimes|nullable|string|max:3',
            'alert_quantity' => 'sometimes|nullable|numeric|min:0',
            'expire_date' => 'sometimes|nullable|date',
        ];
    }
}
