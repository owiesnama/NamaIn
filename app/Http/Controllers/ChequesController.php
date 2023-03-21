<?php

namespace App\Http\Controllers;

use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Vendor;

class ChequesController extends Controller
{
    public function index()
    {
        return inertia('Cheques/Index', ['cheques' => Cheque::with('payee')->get()]);
    }

    public function create()
    {
        $payees = Customer::all()->concat(Vendor::all())->map(function ($payee) {
            $payee->type = get_class($payee);
            $payee->type_string = class_basename($payee);

            return $payee;
        });

        return inertia('Cheques/Create', [
            'payees' => $payees,
        ]);
    }

    public function store()
    {
        Cheque::forPayee(request('payee'))->register([
            'type' => request('type'),
            'amount' => request('amount'),
            'due' => request('due'),
        ]);

        return redirect()->route('cheques.index')->with('flash', ['message' => 'new cheque has been registered']);
    }
}
