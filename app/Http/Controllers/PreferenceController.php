<?php

namespace App\Http\Controllers;

use App\Models\Preference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PreferenceController
{
    public function index()
    {
        $preferences = Preference::asPairs();

        return inertia('Preferences/Show', ['preferences' => $preferences]);
    }

    public function update(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            if ($key === 'logo' && $request->hasFile('logo')) {
                $value = $request->file('logo')->store('logos', 'public');
            }

            if ($value === null) {
                continue;
            }

            Preference::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
        Cache::forget('preferences');

        return back()->with('success', 'Settings updated successfully');
    }
}
