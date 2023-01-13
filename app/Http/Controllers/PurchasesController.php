<?php

namespace App\Http\Controllers;


class PurchasesController extends Controller
{
    public function index()
    {
        return inertia('Purchases');
    }
}
