<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Response;

class MustChangePasswordController extends Controller
{
    public function show(): Response
    {
        return inertia('Auth/MustChangePassword');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        return redirect()->intended(route('dashboard'));
    }
}
