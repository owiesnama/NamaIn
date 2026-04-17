<?php

namespace App\Http\Controllers;

use App\Actions\UpdatePreferences;
use App\Http\Requests\PreferenceRequest;
use App\Models\Preference;
use Inertia\Response;

class PreferenceController extends Controller
{
    public function index(): Response
    {
        return inertia('Preferences/Show', [
            'preferences' => Preference::asPairs(),
        ]);
    }

    public function update(PreferenceRequest $request, UpdatePreferences $action)
    {
        $action->execute($request);

        return back()->with('success', 'Settings updated successfully');
    }
}
