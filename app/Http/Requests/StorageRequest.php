<?php

namespace App\Http\Requests;

use App\Enums\StorageType;
use App\Features\Feature;
use App\Rules\WithinPlanLimit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // On create, block adding a warehouse beyond the plan's max_warehouses cap.
            'name' => $this->route('storage') === null
                ? ['required', new WithinPlanLimit(Feature::MaxWarehouses)]
                : ['required'],
            'address' => 'required',
            'type' => ['required', new Enum(StorageType::class)],
        ];
    }
}
