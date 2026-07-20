<?php

namespace App\Http\Requests\Admin;

use App\Features\Feature;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by EnsureSuperAdmin on the route group
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $planId = $this->route('plan')?->id;

        return [
            'key' => ['required', 'string', 'alpha_dash', 'max:64', Rule::unique('plans', 'key')->ignore($planId)],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'description.ar' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'sort' => ['integer', 'min:0'],
            'features' => ['array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('features', []) as $key => $value) {
                $feature = Feature::tryFrom((string) $key);

                if (! $feature) {
                    $validator->errors()->add("features.{$key}", __('Unknown feature key.'));

                    continue;
                }

                if ($feature->isLimit() && $value !== null && ! is_numeric($value)) {
                    $validator->errors()->add("features.{$key}", __('A limit must be a number or empty for unlimited.'));
                }
            }
        });
    }
}
