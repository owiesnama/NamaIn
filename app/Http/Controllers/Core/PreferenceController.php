<?php

namespace App\Http\Controllers\Core;

use App\Actions\UpdatePreferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreferenceRequest;
use App\Models\Preference;
use Inertia\Response;

class PreferenceController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()->hasRole('owner', 'admin'), 403);

        return inertia('Preferences/Show', [
            'preferences' => Preference::asPairs(),
        ]);
    }

    public function update(PreferenceRequest $request, UpdatePreferences $action)
    {
        abort_unless(auth()->user()->hasRole('owner', 'admin'), 403);
        $action->handle($request);

        return back()->with('success', 'Settings updated successfully');
    }
}
