<?php

namespace App\Http\Controllers\Core;

use App\Actions\UpdatePreferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreferenceRequest;
use Inertia\Response;

class PreferenceController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()->hasRole('owner', 'admin'), 403);

        // Deliberately passes no 'preferences' prop: HandleInertiaRequests already
        // shares one, with the logo path resolved to a URL. A page prop of the same
        // name overrides the shared one, which would hand the raw disk path back to
        // ApplicationLogo and 404 the sidebar logo on this page only.
        return inertia('Preferences/Show');
    }

    public function update(PreferenceRequest $request, UpdatePreferences $action)
    {
        abort_unless(auth()->user()->hasRole('owner', 'admin'), 403);
        $action->handle($request);

        return back()->with('success', __('Settings updated successfully'));
    }
}
