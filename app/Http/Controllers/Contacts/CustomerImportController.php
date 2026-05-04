<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Concerns\HandlesImport;
use App\Http\Controllers\Controller;

class CustomerImportController extends Controller
{
    use HandlesImport;

    protected function importType(): string
    {
        return 'customers';
    }

    protected function allowedTemplates(): array
    {
        return ['default', 'quickbooks'];
    }

    protected function importHeaders(): array
    {
        return ['name', 'address', 'phone_number', 'credit_limit', 'opening_debit', 'opening_credit'];
    }

    protected function importSampleData(): array
    {
        return ['Example Customer', 'Customer Address 123', '0123456789', '5000', '1000', '0'];
    }
}
