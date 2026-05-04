<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Concerns\HandlesImport;
use App\Http\Controllers\Controller;

class SupplierImportController extends Controller
{
    use HandlesImport;

    protected function importType(): string
    {
        return 'suppliers';
    }

    protected function importHeaders(): array
    {
        return ['name', 'address', 'phone_number', 'opening_debit', 'opening_credit'];
    }

    protected function importSampleData(): array
    {
        return ['Example Supplier', 'Supplier Address 123', '0123456789', '0', '1000'];
    }
}
