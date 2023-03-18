<?php

namespace App\Http\Controllers;

use App\Models\Storage;

class StoragesController extends Controller
{
    public function index()
    {
        return inertia('Storages', [
            'storages' => Storage::search(request('search'))
                ->latest()
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function store()
    {
        Storage::create(request()->validate([
            'name' => 'required',
            'address' => 'required',
        ]));

        return back()->with('flash', [
            'title' => 'Storage Created 🎉',
            'message' => 'Storage created successfully',
        ]);
    }

    public function update(Storage $storage)
    {
        $attributes = request()->validate([
            'name' => 'required',
            'address' => 'required',
        ]);

        $storage->update($attributes);

        return back()->with('flash', [
            'title' => 'Storage updated 🎉',
            'message' => 'Storage updated successfully',
        ]);
    }

    public function destory(Storage $storage)
    {
        $storage->delete();

        return back()->with('flash', [
            'title' => 'Storage Created 🎉',
            'message' => 'Storage created successfully',
        ]);
    }
}
