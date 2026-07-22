<?php

namespace App\Http\Requests\Sync;

use App\Enums\StorageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the `can:devices.manage` route middleware
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'storage_id' => [
                'required',
                'integer',
                Rule::exists('storages', 'id')
                    ->where('tenant_id', app('currentTenant')->id)
                    ->where('type', StorageType::SALE_POINT->value),
            ],
        ];
    }
}
