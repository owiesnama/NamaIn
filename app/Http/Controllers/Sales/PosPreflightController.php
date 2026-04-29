<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Pos\PosPreflightAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PosPreflightRequest;
use App\Models\PosSession;

class PosPreflightController extends Controller
{
    public function store(PosPreflightRequest $request, PosPreflightAction $preflight)
    {
        $this->authorize('create', PosSession::class);
        $session = PosSession::findOrFail($request->session_id);

        $result = $preflight->execute($session, $request->items);

        return response()->json($result);
    }
}
