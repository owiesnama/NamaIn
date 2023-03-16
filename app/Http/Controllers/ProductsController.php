<?php

namespace App\Http\Controllers;

class ProductsController extends Controller
{
    public function index()
    {
        return inertia('Products');
    }
}
