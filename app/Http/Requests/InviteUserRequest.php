<?php

namespace App\Http\Requests;

use App\Features\Feature;
use App\Rules\WithinPlanLimit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Inviting adds a seat — block when the plan's max_users cap is reached.
            'email' => ['required', 'email', 'max:255', new WithinPlanLimit(Feature::MaxUsers)],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where('tenant_id', app('currentTenant')->id),
            ],
        ];
    }
}
