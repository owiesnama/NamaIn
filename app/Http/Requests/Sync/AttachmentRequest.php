<?php

namespace App\Http\Requests\Sync;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The attachment upload (Design 02 §7.4): multipart, ≤ 5 MB, jpg|png|pdf,
 * referenced from an expense by `receipt_public_id`. Canonical field names:
 * `receipt_public_id` and `file`.
 */
class AttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'receipt_public_id' => ['required', 'string'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
