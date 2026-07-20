<?php

namespace App\Http\Requests;

use App\Enums\BookingStatus;
use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class BookingRequest extends FormRequest
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
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'service_product_id' => ['required', Rule::exists('products', 'id')->where('type', ProductType::Service->value)],
            'starts_at' => ['required', 'date'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', new Enum(BookingStatus::class)],
            'addons' => ['nullable', 'array'],
            'addons.*' => ['integer'],
            'acknowledge_buffer' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Rules that depend on the selected service: it must be bookable, and an
     * on-site service requires an address.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $service = Product::find($this->input('service_product_id'));

            if (! $service) {
                return;
            }

            if (! $service->requires_booking) {
                $validator->errors()->add('service_product_id', __('This service is not bookable.'));
            }

            if ($service->on_site && blank($this->input('address'))) {
                $validator->errors()->add('address', __('An address is required for on-site services.'));
            }
        });
    }
}
