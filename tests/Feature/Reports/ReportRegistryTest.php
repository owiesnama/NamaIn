<?php

use App\Reports\ReportRegistry;
use App\Reports\SalesReport;

test('registry resolves a report by its slug', function () {
    $report = app(ReportRegistry::class)->resolve('sales');

    expect($report)->toBeInstanceOf(SalesReport::class);
});

test('registry returns null for an unknown slug', function () {
    $report = app(ReportRegistry::class)->resolve('does-not-exist');

    expect($report)->toBeNull();
});
