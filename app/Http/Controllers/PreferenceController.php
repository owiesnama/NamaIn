<?php

namespace App\Http\Controllers;

use App\Models\Preference;
use Illuminate\Support\Facades\Cache;

class PreferenceController
{
    public function index()
    {
        $preferences = Preference::asPairs();

        return inertia('Preferences/Show', ['preferences' => $preferences]);
    }

    public function update()
    {
        foreach (request()->all() as $key => $value) {
            Preference::create([
                'key' => $key,
                'value' => $value,
            ]);
        }
        Cache::delete('preferences');
        return back()->with('success', 'Settings updated successfully');
    }
}
