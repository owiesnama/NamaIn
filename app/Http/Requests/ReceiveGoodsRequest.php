<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveGoodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('purchases.create');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'quantity' => 'required|integer|min:1',
            'storage_id' => 'required|exists:storages,id',
            'notes' => 'nullable|string',
        ];
    }
}
