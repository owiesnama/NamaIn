<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuppliersController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->search($request->get('search'))
            ->latest()
            ->simplePaginate(20);

        return response()->json($suppliers);
    }
}
