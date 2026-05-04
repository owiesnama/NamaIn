<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Concerns\HandlesImport;
use App\Http\Controllers\Controller;

class ProductImportController extends Controller
{
    use HandlesImport;

    protected function importType(): string
    {
        return 'products';
    }

    protected function allowedTemplates(): array
    {
        return ['default', 'quickbooks'];
    }

    protected function importHeaders(): array
    {
        return ['name', 'cost', 'price', 'currency', 'expire_date', 'unit_name', 'unit_conversion_factor', 'categories'];
    }

    protected function importSampleData(): array
    {
        return ['Example Product', '100', '120', 'SDG', '2026-12-31', 'Box', '10', 'Category1,Category2'];
    }
}
