<?php

namespace App\Http\Controllers\Utils;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemporaryUploadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if (! $request->hasFile('receipt')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        $file = $request->file('receipt');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('tmp', $filename, 'local');

        return response($filename, 200)
            ->header('Content-Type', 'text/plain');
    }

    public function destroy(Request $request)
    {
        $filename = $request->getContent();

        if (! $filename) {
            return response()->json(['error' => 'No filename provided'], 400);
        }

        // Security check: ensure the filename is just a filename and not a path
        if (str_contains($filename, '/') || str_contains($filename, '\\')) {
            return response()->json(['error' => 'Invalid filename'], 400);
        }

        if (Storage::disk('local')->exists('tmp/'.$filename)) {
            Storage::disk('local')->delete('tmp/'.$filename);

            return response('', 204);
        }

        return response()->json(['error' => 'File not found'], 404);
    }
}
