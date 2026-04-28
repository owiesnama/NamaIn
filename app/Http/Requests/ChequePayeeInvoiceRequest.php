<?php

namespace App\Http\Requests;

use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChequePayeeInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Cheque::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'payee_id' => ['required', 'integer'],
            'payee_type' => ['required', 'string', Rule::in([
                'Customer',
                Customer::class,
                'Supplier',
                Supplier::class,
            ])],
        ];
    }

    public function payeeId(): int
    {
        return (int) $this->validated('payee_id');
    }

    public function payeeType(): string
    {
        return match ($this->validated('payee_type')) {
            'Customer' => Customer::class,
            'Supplier' => Supplier::class,
            default => $this->validated('payee_type'),
        };
    }
}
