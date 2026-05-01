<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
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
            'address' => 'required|string|min:10',
            'phone_number' => 'required|numeric|min:10',
            'credit_limit' => 'nullable|numeric|min:0',
            'opening_debit' => $this->isMethod('POST') ? 'nullable|numeric|min:0' : 'prohibited',
            'opening_credit' => $this->isMethod('POST') ? 'nullable|numeric|min:0' : 'prohibited',
            'categories' => 'nullable|array',
            'categories.*.id' => 'required',
            'categories.*.name' => 'required',
        ];
    }
}
