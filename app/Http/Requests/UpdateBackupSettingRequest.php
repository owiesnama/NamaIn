<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBackupSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
            'frequency' => ['required', 'string', Rule::in(['daily', 'weekly', 'monthly', 'custom'])],
            'cron_expression' => ['required_if:frequency,custom', 'nullable', 'string', 'max:100'],
            'retention_count' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
