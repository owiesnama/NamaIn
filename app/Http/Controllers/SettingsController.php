<?php

namespace App\Http\Controllers;

use App\Models\Preference;

class SettingsController
{
    public function index()
    {
        return inertia('Settings/Show');
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
