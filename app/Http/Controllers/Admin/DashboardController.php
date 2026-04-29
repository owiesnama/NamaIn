<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return inertia('Admin/Dashboard', [
            'stats' => [
                'totalTenants' => Tenant::count(),
                'activeTenants' => Tenant::where('is_active', true)->count(),
                'inactiveTenants' => Tenant::where('is_active', false)->count(),
                'totalUsers' => User::count(),
            ],
        ]);
    }
}
