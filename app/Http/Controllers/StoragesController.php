<?php

namespace App\Http\Controllers;

use App\Models\Storage;

class StoragesController extends Controller
{
    public function index()
    {
        return inertia('Storages', [
            'storages_count' => Storage::count(),
            'storages' => Storage::search(request('search'))
                ->latest()
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function store()
    {
        $data = request()->validate([
            'name' => 'required',
            'address' => 'required',
        ]);

        Storage::create($data);

        return back()->with('success', 'Storage created successfully');
    }

    public function update(Storage $storage)
    {
        $data = request()->validate([
            'name' => 'required',
            'address' => 'required',
        ]);

        $storage->update($data);

        return back()->with('success', 'Storage updated successfully');
    }

    public function destory(Storage $storage)
    {
        $storage->delete();

        return back()->with('success', 'Storage Deleted successfully');
    }
}
