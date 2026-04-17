<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'expensed_at' => 'required|date',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'nullable',
            'category_objects' => 'nullable|array',
            'category_objects.*.name' => 'required_with:category_objects|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'receipt' => 'nullable|string|max:255',
            'is_recurring' => 'nullable|boolean',
            'frequency' => 'required_if:is_recurring,true|nullable|in:daily,weekly,monthly,yearly',
            'starts_at' => 'required_if:is_recurring,true|nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ];
    }
}
