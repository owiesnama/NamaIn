<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdatePricesRequest extends FormRequest
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
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'mode' => ['required', Rule::in(['set', 'percent'])],
            'value' => [
                'required',
                'numeric',
                $this->input('mode') === 'percent' ? 'min:-100' : 'min:0',
            ],
        ];
    }
}
