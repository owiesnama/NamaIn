<?php

namespace App\Http\Controllers\Exports;

use App\Actions\RequestExportAction;
use App\Http\Controllers\Controller;
use App\Models\ExportLog;
use App\Services\ExportRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isManager = $user->hasRole('owner') || $user->hasRole('manager');

        $exports = ExportLog::query()
            ->when(! $isManager, fn ($q) => $q->where('user_id', $user->id))
            ->with('user:id,name')
            ->latest()
            ->paginate(self::ELEMENTS_PER_PAGE);

        return inertia('Exports/Index', [
            'exports' => $exports,
        ]);
    }

    public function store(Request $request, RequestExportAction $action)
    {
        $request->validate([
            'export_key' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! ExportRegistry::isValid($value)) {
                    $fail('Invalid export type.');
                }
            }],
            'format' => ['sometimes', 'string', 'in:xlsx,csv,pdf'],
            'filters' => ['sometimes', 'array'],
        ]);

        $exportLog = $action->execute(
            $request->input('export_key'),
            $request->input('format', 'xlsx'),
            $request->input('filters', []),
        );

        return back()->with('flash', [
            'type' => 'export_queued',
            'export_id' => $exportLog->id,
            'message' => __('Export queued. You will be notified when it is ready.'),
        ]);
    }

    public function download(ExportLog $exportLog)
    {
        $this->authorize('download', $exportLog);

        abort_if($exportLog->isExpired(), 410, __('This export has expired.'));
        abort_unless($exportLog->isCompleted(), 404);

        return Storage::download($exportLog->path, $exportLog->filename);
    }
}
