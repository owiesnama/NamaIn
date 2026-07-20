<?php

namespace App\Http\Requests;

use App\Features\Feature;
use App\Rules\WithinPlanLimit;
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
     * Whether this request is creating a product (vs updating an existing one).
     */
    private function isCreating(): bool
    {
        return $this->route('product') === null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return [
            // On create, block adding a product beyond the plan's max_products cap.
            'name' => $this->isCreating()
                ? ['required', new WithinPlanLimit(Feature::MaxProducts)]
                : ['required'],
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
