<?php

use App\Reports\Report;
use App\Reports\ReportRegistry;
use App\Reports\SalesReport;

test('registry resolves a report by its slug', function () {
    $report = app(ReportRegistry::class)->resolve('sales');

    expect($report)->toBeInstanceOf(SalesReport::class);
});

test('registry resolves every registered slug to a report', function () {
    $registry = app(ReportRegistry::class);

    foreach (ReportRegistry::slugs() as $slug) {
        expect($registry->resolve($slug))->toBeInstanceOf(Report::class);
    }
});

test('registry returns null for an unknown slug', function () {
    $report = app(ReportRegistry::class)->resolve('does-not-exist');

    expect($report)->toBeNull();
});
