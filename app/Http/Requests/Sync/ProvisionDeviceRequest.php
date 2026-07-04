<?php

namespace App\Http\Requests\Sync;

use Illuminate\Foundation\Http\FormRequest;

class ProvisionDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the pairing code itself is the credential
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'pairing_code' => ['required', 'string', 'max:64'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ];
    }
}
