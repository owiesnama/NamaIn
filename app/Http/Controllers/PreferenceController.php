<?php

namespace App\Http\Controllers;

use App\Models\Preference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PreferenceController extends Controller
{
    private const ALLOWED_KEYS = [
        'app_name',
        'currency',
        'logo',
        'address',
        'phone',
        'email',
        'footer_text',
        'min_stock_alert',
    ];

    public function index()
    {
        $preferences = Preference::asPairs();

        return inertia('Preferences/Show', ['preferences' => $preferences]);
    }

    public function update(Request $request)
    {
        $validatedData = $request->validate([
            'app_name' => 'nullable|string|max:255',
            'currency' => 'nullable|string|max:10',
            'logo' => 'nullable|image|max:2048',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'footer_text' => 'nullable|string|max:500',
            'min_stock_alert' => 'nullable|numeric|min:0',
        ]);

        foreach ($validatedData as $key => $value) {
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
