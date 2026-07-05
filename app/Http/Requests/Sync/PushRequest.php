<?php

namespace App\Http\Requests\Sync;

use App\Enums\MutationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * The push envelope (Design 02 §5.1): an ordered mutation array, capped at 200
 * per batch (§7.2). `client_pushed_at` (§8.5) is when the device worker began
 * the push; it is recorded in the sync audit trail.
 */
class PushRequest extends FormRequest
{
    public const BATCH_CAP = 200;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'protocol' => ['nullable', 'integer'],
            'app_version' => ['nullable', 'string'],
            'client_pushed_at' => ['nullable', 'date'],
            'mutations' => ['required', 'array', 'min:1', 'max:'.self::BATCH_CAP],
            'mutations.*.idempotency_key' => ['required', 'string'],
            'mutations.*.type' => ['required', new Enum(MutationType::class)],
            'mutations.*.public_id' => ['required', 'string'],
            'mutations.*.actor' => ['required', 'string'],
            'mutations.*.occurred_at' => ['nullable', 'date'],
            'mutations.*.payload' => ['required', 'array'],
        ];
    }
}
