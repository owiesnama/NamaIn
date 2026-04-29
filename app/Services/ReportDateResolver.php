<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportDateResolver
{
    /** @return array{from: Carbon, to: Carbon} */
    public function resolve(Request $request): array
    {
        if ($request->filled('from_date') && $request->filled('to_date')) {
            return [
                'from' => Carbon::parse($request->input('from_date'))->startOfDay(),
                'to' => Carbon::parse($request->input('to_date'))->endOfDay(),
            ];
        }

        return $this->resolvePreset($request->input('preset', 'this_month'));
    }

    /** @return array{from: Carbon, to: Carbon} */
    public function resolvePreset(string $preset): array
    {
        return match ($preset) {
            'today' => [
                'from' => now()->startOfDay(),
                'to' => now()->endOfDay(),
            ],
            'yesterday' => [
                'from' => now()->subDay()->startOfDay(),
                'to' => now()->subDay()->endOfDay(),
            ],
            'this_week' => [
                'from' => now()->startOfWeek(),
                'to' => now()->endOfWeek(),
            ],
            'last_week' => [
                'from' => now()->subWeek()->startOfWeek(),
                'to' => now()->subWeek()->endOfWeek(),
            ],
            'last_month' => [
                'from' => now()->subMonth()->startOfMonth(),
                'to' => now()->subMonth()->endOfMonth(),
            ],
            'this_quarter' => [
                'from' => now()->startOfQuarter(),
                'to' => now()->endOfQuarter(),
            ],
            'last_quarter' => [
                'from' => now()->subQuarter()->startOfQuarter(),
                'to' => now()->subQuarter()->endOfQuarter(),
            ],
            'this_year' => [
                'from' => now()->startOfYear(),
                'to' => now()->endOfYear(),
            ],
            'last_30_days' => [
                'from' => now()->subDays(30)->startOfDay(),
                'to' => now()->endOfDay(),
            ],
            default => [ // this_month
                'from' => now()->startOfMonth(),
                'to' => now()->endOfMonth(),
            ],
        };
    }

    /** @return array<string, string> */
    public static function presets(): array
    {
        return [
            'today' => __('Today'),
            'yesterday' => __('Yesterday'),
            'this_week' => __('This Week'),
            'last_week' => __('Last Week'),
            'this_month' => __('This Month'),
            'last_month' => __('Last Month'),
            'this_quarter' => __('This Quarter'),
            'last_quarter' => __('Last Quarter'),
            'this_year' => __('This Year'),
            'last_30_days' => __('Last 30 Days'),
        ];
    }
}
