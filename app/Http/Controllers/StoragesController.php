<?php

namespace App\Http\Controllers;


class StoragesController extends Controller
{
    public function index()
    {
        return inertia('Storages');
    }
}
