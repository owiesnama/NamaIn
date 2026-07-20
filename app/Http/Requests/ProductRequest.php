<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use App\Features\Feature;
use App\Rules\WithinPlanLimit;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

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
     * Normalise the service flags to real booleans before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->isService()) {
            $this->merge([
                'requires_booking' => $this->boolean('requires_booking'),
                'on_site' => $this->boolean('on_site'),
                'allow_overlap' => $this->boolean('allow_overlap'),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Services are priced by their base price (no `cost > 0` requirement) and
     * carry their own attributes; physical products keep exactly today's rules.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        // On create, block adding a product beyond the plan's max_products cap.
        $nameRule = $this->isCreating()
            ? ['required', new WithinPlanLimit(Feature::MaxProducts)]
            : ['required'];

        if ($this->isService()) {
            return [
                'type' => [new Enum(ProductType::class)],
                'name' => $nameRule,
                'price' => 'required|numeric|min:0',
                'currency' => 'nullable|string|max:3',
                'duration_minutes' => 'nullable|integer|min:1',
                'requires_booking' => 'boolean',
                'on_site' => 'boolean',
                'allow_overlap' => 'boolean',
                'travel_buffer_minutes' => 'nullable|integer|min:0',
                'addons' => 'nullable|array',
                'addons.*.name' => 'required_with:addons',
                'addons.*.price_delta' => 'required_with:addons|numeric|min:0',
                'categories' => 'nullable|array',
                'categories.*.id' => 'required',
                'categories.*.name' => 'required',
            ];
        }

        return [
            'type' => ['nullable', 'in:'.ProductType::Physical->value],
            'name' => $nameRule,
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

    /**
     * Conditionally require the scheduling attributes only where they matter:
     * a bookable service needs a positive duration, and an on-site service
     * needs a travel buffer.
     */
    public function withValidator($validator): void
    {
        $validator->sometimes(
            'duration_minutes',
            'required|integer|min:1',
            fn () => $this->isService() && $this->boolean('requires_booking'),
        );

        $validator->sometimes(
            'travel_buffer_minutes',
            'required|integer|min:0',
            fn () => $this->isService() && $this->boolean('on_site'),
        );
    }

    /**
     * Whether this request describes a service product.
     */
    private function isService(): bool
    {
        return $this->input('type') === ProductType::Service->value;
    }

    /**
     * Whether this request is creating a product (vs updating an existing one).
     */
    private function isCreating(): bool
    {
        return $this->route('product') === null;
    }
}
