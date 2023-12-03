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
            'products' => $storage->stock()->get(),
        ]);
    }

    public function store(StorageRequest $request)
    {
        Storage::create($request->all());

        return back()->with('success', __('Storage created successfully'));
    }

    public function update(Storage $storage, StorageRequest $request)
    {
        $storage->update($request->all());

        return back()->with('success', __('Storage updated successfully'));
    }

    public function destroy(Storage $storage)
    {
        $this->authorize('delete');

        $storage->delete();

        return back()->with('success', __('Storage Deleted successfully'));
    }
}
