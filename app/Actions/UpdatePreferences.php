<?php

namespace App\Actions;

use App\Http\Requests\PreferenceRequest;
use App\Models\Preference;
use Illuminate\Support\Facades\Cache;

class UpdatePreferences
{
    public function execute(PreferenceRequest $request): void
    {
        foreach ($request->validated() as $key => $value) {
            $value = $this->resolveValue($key, $value, $request);

            if ($value === null) {
                continue;
            }

            Preference::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        Cache::forget('preferences');
    }

    private function resolveValue(string $key, mixed $value, PreferenceRequest $request): mixed
    {
        if ($key !== 'logo' || ! $request->hasFile('logo')) {
            return $value;
        }

        return $request->file('logo')->store('logos', 'public');
    }
}
