<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class NotificationsController extends Controller
{
    public function index(Request $request): Response
    {
        return inertia('Notifications/Index', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        return response()->json([
            'items' => $request->user()->notifications()->latest()->limit(10)->get(),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
