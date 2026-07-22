<?php

use App\Enums\ReconciliationType;
use App\Enums\ResolutionKind;
use App\Exports\Reports\ReconciliationReportExport;
use App\Models\ReconciliationItem;
use App\Services\Utils\ExportRegistry;

it('registers report-reconciliation in the export registry', function () {
    expect(ExportRegistry::isValid('report-reconciliation'))->toBeTrue();
    expect(ExportRegistry::resolve('report-reconciliation'))->toBe(ReconciliationReportExport::class);
});

it('exports item-level detail with the occurred-to-resolved timeline', function () {
    ReconciliationItem::factory()->create([
        'type' => ReconciliationType::Oversell,
        'detected_at' => now(),
        'occurred_at' => now()->subHour(),
    ]);
    ReconciliationItem::factory()->resolved()->create([
        'type' => ReconciliationType::CreditBreach,
        'resolution' => ResolutionKind::Acknowledge,
        'detected_at' => now(),
    ]);

    $rows = (new ReconciliationReportExport([
        'from_date' => now()->subDay()->toDateString(),
        'to_date' => now()->addDay()->toDateString(),
    ]))->array();

    expect($rows)->toHaveCount(2);
    expect(collect($rows)->pluck('type')->all())->toEqualCanonicalizing(['oversell', 'credit_breach']);

    $resolved = collect($rows)->firstWhere('status', 'resolved');
    expect($resolved['resolution'])->toBe('acknowledge');
    expect($resolved['resolved_at'])->not->toBeNull();
});

it('excludes items outside the date window', function () {
    ReconciliationItem::factory()->create(['detected_at' => now()->subDays(60)]);

    $rows = (new ReconciliationReportExport([
        'from_date' => now()->subDay()->toDateString(),
        'to_date' => now()->toDateString(),
    ]))->array();

    expect($rows)->toHaveCount(0);
});
