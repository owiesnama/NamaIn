<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResendVerificationController extends Controller
{
    /**
     * Resend the email verification notification for the current tenant user.
     *
     * Fortify's verification.send route lives on the main domain, so the
     * tenant-subdomain dashboard banner cannot reach it with a same-origin,
     * authenticated request. This endpoint dispatches the identical
     * verification notification from within the tenant context.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return back();
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
