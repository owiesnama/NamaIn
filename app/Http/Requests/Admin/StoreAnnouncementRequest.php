<?php

namespace App\Http\Requests\Admin;

use App\Enums\AnnouncementAudience;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'audience_type' => ['required', Rule::enum(AnnouncementAudience::class)],
            'tenant_id' => [
                'required_if:audience_type,tenant,tenant_role',
                'nullable',
                'exists:tenants,id',
            ],
            'role_id' => [
                'required_if:audience_type,tenant_role',
                'nullable',
                Rule::exists('roles', 'id')->where('tenant_id', $this->input('tenant_id')),
            ],
            'user_ids' => ['required_if:audience_type,users', 'nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
            'send_email' => ['boolean'],
        ];
    }
}
