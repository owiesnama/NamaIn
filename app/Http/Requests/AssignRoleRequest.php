<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where('tenant_id', app('currentTenant')->id),
            ],
        ];
    }
}
