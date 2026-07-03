<?php

namespace App\Reports;

use Illuminate\Contracts\Container\Container;

class ReportRegistry
{
    /**
     * Slug-to-report map; the single source of truth for available reports.
     *
     * @var array<string, class-string<Report>>
     */
    private const REPORTS = [
        'sales' => SalesReport::class,
    ];

    public function __construct(private Container $container) {}

    public function resolve(string $slug): ?Report
    {
        $class = self::REPORTS[$slug] ?? null;

        if ($class === null) {
            return null;
        }

        return $this->container->make($class);
    }
}
