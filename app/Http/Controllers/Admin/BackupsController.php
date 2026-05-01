<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\LogAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBackupRequest;
use App\Jobs\RunBackupJob;
use App\Models\Backup;
use App\Models\BackupSetting;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupsController extends Controller
{
    public function __construct(private LogAdminAction $logger) {}

    public function index(): Response
    {
        $backups = Backup::query()
            ->with(['tenant:id,name,slug', 'creator:id,name'])
            ->when(request('type'), fn ($q, $type) => $q->where('type', $type))
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return inertia('Admin/Backups', [
            'backups' => $backups,
            'tenants' => Tenant::select('id', 'name', 'slug')->orderBy('name')->get(),
            'settings' => BackupSetting::resolve(),
            'filters' => request()->only('type', 'status'),
        ]);
    }

    public function store(StoreBackupRequest $request): RedirectResponse
    {
        $tenant = $request->type === 'tenant'
            ? Tenant::findOrFail($request->tenant_id)
            : null;

        $filename = $request->type === 'tenant'
            ? "tenant_{$tenant->id}_{$tenant->slug}_".now()->format('Y_m_d_His').".{$request->format}"
            : 'full_'.now()->format('Y_m_d_His').'.dump';

        $backup = Backup::create([
            'type' => $request->type,
            'format' => $request->type === 'full' ? 'dump' : $request->format,
            'tenant_id' => $tenant?->id,
            'filename' => $filename,
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        RunBackupJob::dispatch($backup);

        $this->logger->handle($request->user()->id, 'backup.created', $backup, [
            'type' => $backup->type,
            'tenant' => $tenant?->name,
        ]);

        return back()->with('success', __('Backup has been queued.'));
    }

    public function show(Backup $backup): BinaryFileResponse
    {
        if (! file_exists($backup->path)) {
            abort(404, __('Backup file not found.'));
        }

        return response()->download($backup->path, $backup->filename);
    }

    public function destroy(Backup $backup): RedirectResponse
    {
        if (file_exists($backup->path)) {
            unlink($backup->path);
        }

        $this->logger->handle(auth('admin')->id(), 'backup.deleted', $backup);

        $backup->delete();

        return back()->with('success', __('Backup deleted.'));
    }
}
