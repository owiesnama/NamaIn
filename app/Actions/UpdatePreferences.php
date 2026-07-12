<?php

namespace App\Actions;

use App\Facades\Cache;
use App\Http\Requests\PreferenceRequest;
use App\Models\Preference;
use App\Models\TenantSettingHistory;

class UpdatePreferences
{
    /**
     * Preference keys whose changes are recorded to the tenant settings history
     * so reports can explain rule changes that happened mid-history.
     *
     * @var array<int, string>
     */
    private const AUDITED_KEYS = ['inventory_strategy', 'allow_overselling'];

    public function handle(PreferenceRequest $request): void
    {
        foreach ($request->validated() as $key => $value) {
            $value = $this->resolveValue($key, $value, $request);

            if ($value === null) {
                continue;
            }

            $this->persist($key, $value);
        }

        Cache::forget('preferences');
    }

    private function persist(string $key, mixed $value): void
    {
        $audited = in_array($key, self::AUDITED_KEYS, true);
        $oldValue = $audited ? Preference::where('key', $key)->value('value') : null;

        Preference::updateOrCreate(['key' => $key], ['value' => $value]);

        if ($audited && (string) $oldValue !== (string) $value) {
            TenantSettingHistory::create([
                'key' => $key,
                'old_value' => $oldValue,
                'new_value' => $value,
                'changed_by' => auth()->id(),
            ]);
        }
    }

    private function resolveValue(string $key, mixed $value, PreferenceRequest $request): mixed
    {
        if ($key !== 'logo' || ! $request->hasFile('logo')) {
            return $value;
        }

        return $request->file('logo')->store('logos', 'public');
    }
}
