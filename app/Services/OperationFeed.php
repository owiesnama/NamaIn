<?php

namespace App\Services;

use App\Enums\ExportStatus;
use App\Models\ExportLog;
use App\Models\ImportLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OperationFeed
{
    private const LIMIT = 10;

    private const RECENT_FINISHED_MINUTES = 30;

    public function forUser(User $user, Tenant $tenant): array
    {
        return $this->exports($user, $tenant)
            ->concat($this->imports($user, $tenant))
            ->sortByDesc('updated_at')
            ->take(self::LIMIT)
            ->map(fn (array $operation) => collect($operation)->except('updated_at')->all())
            ->values()
            ->all();
    }

    private function exports(User $user, Tenant $tenant): Collection
    {
        return $this->visible(ExportLog::query())
            ->whereBelongsTo($tenant)
            ->whereBelongsTo($user)
            ->latest('updated_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (ExportLog $exportLog) => $this->formatExport($exportLog));
    }

    private function imports(User $user, Tenant $tenant): Collection
    {
        return $this->visible(ImportLog::query())
            ->whereBelongsTo($tenant)
            ->whereBelongsTo($user)
            ->latest('updated_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (ImportLog $importLog) => $this->formatImport($importLog));
    }

    private function visible(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereIn('status', $this->activeStatuses())
                ->orWhere(fn (Builder $query) => $query
                    ->whereIn('status', $this->finishedStatuses())
                    ->where('updated_at', '>=', now()->subMinutes(self::RECENT_FINISHED_MINUTES)));
        });
    }

    private function formatExport(ExportLog $exportLog): array
    {
        return [
            'id' => $exportLog->id,
            'kind' => 'export',
            'label' => $exportLog->export_key,
            'status' => $exportLog->status->value,
            'filename' => $exportLog->filename,
            'failure_message' => $exportLog->failure_message,
            'updated_at' => $exportLog->updated_at,
        ];
    }

    private function formatImport(ImportLog $importLog): array
    {
        return [
            'id' => "import-{$importLog->id}",
            'kind' => 'import',
            'label' => $importLog->import_type,
            'status' => $importLog->status->value,
            'rows_imported' => $importLog->rows_imported,
            'failure_message' => $importLog->failure_message,
            'updated_at' => $importLog->updated_at,
        ];
    }

    private function activeStatuses(): array
    {
        return [
            ExportStatus::Queued->value,
            ExportStatus::Processing->value,
        ];
    }

    private function finishedStatuses(): array
    {
        return [
            ExportStatus::Completed->value,
            ExportStatus::Failed->value,
        ];
    }
}
