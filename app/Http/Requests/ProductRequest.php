<?php

namespace App\Http\Requests;

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
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required',
            'cost' => 'required|numeric|gt:0',
            'expire_date' => 'required',
            'units' => 'array|min:1',
            'units.*.name' => 'required',
            'units.*.conversion_factor' => 'required|numeric|gt:0',
        ];
    }
}
