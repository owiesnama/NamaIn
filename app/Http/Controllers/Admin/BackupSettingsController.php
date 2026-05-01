<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\LogAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBackupSettingRequest;
use App\Models\BackupSetting;
use Illuminate\Http\RedirectResponse;

class BackupSettingsController extends Controller
{
    public function __construct(private LogAdminAction $logger) {}

    public function update(UpdateBackupSettingRequest $request): RedirectResponse
    {
        $settings = BackupSetting::resolve();

        $settings->update([
            'is_enabled' => $request->is_enabled,
            'frequency' => $request->frequency,
            'cron_expression' => $request->frequency === 'custom' ? $request->cron_expression : null,
            'retention_count' => $request->retention_count,
            'updated_by' => $request->user()->id,
        ]);

        $this->logger->handle($request->user()->id, 'backup.settings_updated', $settings, [
            'frequency' => $settings->frequency,
            'is_enabled' => $settings->is_enabled,
        ]);

        return back()->with('success', __('Backup settings updated.'));
    }
}
