<?php

namespace App\Http\Controllers;

use App\Filters\StorageFilter;
use App\Http\Requests\StorageRequest;
use App\Models\Storage;

class StoragesController extends Controller
{
    public function index(StorageFilter $filter)
    {
        return inertia('Storages/Index', [
            'storages_count' => Storage::count(),
            'storages' => Storage::filter($filter)
                ->when(request('sort_by'), function ($query, $sortBy) {
                    $query->orderBy(in_array($sortBy, ['name', 'created_at']) ? $sortBy : 'created_at', request('sort_order', 'desc'));
                }, function ($query) {
                    $query->latest();
                })
                ->withCount('stock')
                ->paginate(parent::ELEMENTS_PER_PAGE)
                ->withQueryString(),
        ]);
    }

    public function show(Storage $storage)
    {
        return inertia('Storages/Show', [
            'storage' => $storage,
            'products' => $storage->stock()
                ->when(request('search'), function ($query, $search) {
                    $query->where('name', config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE', "%{$search}%");
                })
                ->paginate(parent::ELEMENTS_PER_PAGE)
                ->withQueryString(),
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
        $this->authorize('delete', $storage);

        $storage->delete();

        return back()->with('success', __('Storage Deleted successfully'));
    }
}
