<?php

namespace App\Http\Requests;

use App\Enums\ChequeStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateChequeStatusRequest extends FormRequest
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
        $cheque = $this->route('cheque');

        return [
            'status' => ['required', new Enum(ChequeStatus::class)],
            'cleared_amount' => 'required_if:status,partially_cleared|numeric|min:0.01|max:'.($cheque->amount - $cheque->cleared_amount),
        ];
    }
}
