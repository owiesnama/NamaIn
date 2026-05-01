<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo' => 'nullable|image|max:2048',
            'invoicesHeadline' => 'nullable|string|max:500',
            'alerts' => 'nullable',
            'currency' => 'nullable|string|max:10',
            'pecentage' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
