<?php

namespace App\Http\Requests\Sync;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The heartbeat body (Design 02 §8.2, §8.5): a lightweight health beat the
 * device sends on an idle tick carrying its outbox backlog and pilot SLO
 * counters. Every field is optional so a minimal beat still refreshes
 * last_seen_at.
 */
class HeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pending_count' => ['nullable', 'integer', 'min:0'],
            'oldest_pending_at' => ['nullable', 'date'],
            'app_version' => ['nullable', 'string'],
            'crash_count' => ['nullable', 'integer', 'min:0'],
            'session_count' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
