<?php

namespace App\Http\Controllers;


class SalesController extends Controller
{
    public function index()
    {
        return inertia('Sales');
    }
}
