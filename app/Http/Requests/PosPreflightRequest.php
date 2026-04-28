<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosPreflightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('pos.operate');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'session_id' => 'required|exists:pos_sessions,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_id' => 'nullable|exists:units,id',
        ];
    }
}
