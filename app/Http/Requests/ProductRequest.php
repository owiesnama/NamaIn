<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
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
            'name' => 'required',
            'cost' => 'required|numeric|gt:0',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'expire_date' => 'nullable|date',
            'alert_quantity' => 'nullable|numeric|min:0',
            'quantities' => 'nullable|array',
            'quantities.*.storage_id' => 'required_with:quantities|integer|exists:storages,id',
            'quantities.*.quantity' => 'required_with:quantities|integer|min:0',
            'units' => 'nullable|array',
            'units.*.name' => 'required_with:units',
            'units.*.conversion_factor' => 'required_with:units|numeric|gt:0',
            'categories' => 'nullable|array',
            'categories.*.id' => 'required',
            'categories.*.name' => 'required',
        ];
    }
}
