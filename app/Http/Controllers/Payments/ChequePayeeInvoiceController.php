<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChequePayeeInvoiceRequest;
use App\Queries\ChequePayeeLookupQuery;
use Illuminate\Http\JsonResponse;

class ChequePayeeInvoiceController extends Controller
{
    public function index(ChequePayeeInvoiceRequest $request, ChequePayeeLookupQuery $payees): JsonResponse
    {
        return response()->json(
            $payees->outstandingInvoicesFor($request->payeeId(), $request->payeeType())
        );
    }
}
