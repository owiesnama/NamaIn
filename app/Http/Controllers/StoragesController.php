<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorageRequest;
use App\Models\Storage;

class StoragesController extends Controller
{
    public function index()
    {
        return inertia('Storages/Index', [
            'storages_count' => Storage::count(),
            'storages' => Storage::search(request('search'))
                ->latest()
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function show(Storage $storage)
    {
        return inertia('Storages/Show', [
            'storage' => $storage,
        ]);
    }

    public function store(StorageRequest $request)
    {
        Storage::create($request->all());

        return back()->with('success', 'Storage created successfully');
    }

    public function update(Storage $storage, StorageRequest $request)
    {
        $storage->update($request->all());

        return back()->with('success', 'Storage updated successfully');
    }

    public function destroy(Storage $storage)
    {
        $storage->delete();

        return back()->with('success', 'Storage Deleted successfully');
    }
}
