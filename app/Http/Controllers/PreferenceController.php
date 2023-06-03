<?php

namespace App\Http\Controllers;

use App\Models\Preference;

class PreferenceController
{
    public function index()
    {
        $preferences = Preference::all()->transform(function ($preference) {
            return [$preference->key => $preference->value];
        })->mapWithKeys(fn ($i) => $i);
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

        return back()->with('success', 'Settings updated successfully');
    }
}
