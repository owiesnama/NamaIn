<?php

namespace App\Http\Requests;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice' => [
                'required',
                'exists:invoices,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $invoice = Invoice::find($value);

                    if ($invoice && $invoice->status === InvoiceStatus::Delivered) {
                        $fail(__('This invoice has already been fully delivered.'));
                    }
                },
            ],
        ];
    }
}
