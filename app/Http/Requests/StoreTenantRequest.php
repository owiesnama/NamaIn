<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:63', 'alpha_dash:ascii',
                Rule::unique('tenants', 'slug'),
                Rule::notIn(Tenant::RESERVED_SLUGS),
            ],
            'owner_email' => ['required', 'email', 'exists:users,email'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('slug')) {
            $this->merge(['slug' => strtolower($this->slug)]);
        }
    }
}
