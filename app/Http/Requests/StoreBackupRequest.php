<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['tenant', 'full'])],
            'format' => ['required', 'string', Rule::in(['sql', 'json', 'dump'])],
            'tenant_id' => ['required_if:type,tenant', 'nullable', 'exists:tenants,id'],
        ];
    }
}
