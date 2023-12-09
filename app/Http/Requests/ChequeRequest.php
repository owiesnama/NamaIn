<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ChequeRequest extends FormRequest
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
            'payee' => 'required|array',
            'payee.type' => 'required|string',
            'type' => 'required|in:1,0',
            'due' => 'required|date',
            'bank' => 'required|string|min:10',
            'amount' => 'required|numeric',
        ];
    }
}
