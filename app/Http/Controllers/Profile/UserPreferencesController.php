<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserPreferencesController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'language' => ['nullable', 'string', 'in:en,ar'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'dateFormat' => ['nullable', 'string', 'max:50'],
        ]);

        $current = $request->user()->user_preferences ?? [];

        $request->user()->update([
            'user_preferences' => array_merge($current, $validated),
        ]);

        return back()->with('success', __('Personal preferences updated.'));
    }
}
