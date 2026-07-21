<?php

namespace App\Http\Requests;

use App\Enums\InventoryStrategyType;
use App\Enums\NumeralSystem;
use App\Enums\StorageType;
use App\Enums\TreasuryAccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $tenantId = app('currentTenant')->id;

        return [
            'logo' => 'nullable|image|max:2048',
            'invoicesHeadline' => 'nullable|string|max:500',
            'alerts' => 'nullable',
            'currency' => 'nullable|string|max:10',
            'numerals' => ['nullable', Rule::enum(NumeralSystem::class)],
            'pecentage' => 'nullable|numeric|min:0|max:100',
            'inventory_strategy' => ['nullable', Rule::enum(InventoryStrategyType::class)],
            'allow_overselling' => 'nullable|boolean',
            'pos_default_cash_account_id' => [
                'nullable',
                Rule::exists('treasury_accounts', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('type', TreasuryAccountType::Cash->value),
            ],
            'pos_default_bank_account_id' => [
                'nullable',
                Rule::exists('treasury_accounts', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('type', TreasuryAccountType::Bank->value),
            ],
            'pos_default_sale_point_id' => [
                'nullable',
                Rule::exists('storages', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('type', StorageType::SALE_POINT->value),
            ],
        ];
    }
}
