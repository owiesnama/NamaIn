<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAdjustStockRequest extends FormRequest
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
            'storage_id' => ['required', 'integer', 'exists:storages,id'],
            'delta' => ['required', 'integer', 'not_in:0'],
            'type' => ['required', Rule::in(['manual', 'damage', 'loss', 'correction'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
