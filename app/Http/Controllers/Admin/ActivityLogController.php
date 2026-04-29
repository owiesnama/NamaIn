<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(): Response
    {
        $logs = AdminAuditLog::query()
            ->with('admin:id,name,email')
            ->when(request('action'), fn ($q, $action) => $q->where('action', $action))
            ->when(request('admin_id'), fn ($q, $id) => $q->where('admin_user_id', $id))
            ->when(request('search'), fn ($q, $search) => $q->whereHas('admin', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $actions = AdminAuditLog::query()
            ->distinct()
            ->pluck('action')
            ->sort()
            ->values();

        return inertia('Admin/ActivityLog', [
            'logs' => $logs,
            'actions' => $actions,
            'filters' => request()->only('action', 'admin_id', 'search'),
        ]);
    }
}
