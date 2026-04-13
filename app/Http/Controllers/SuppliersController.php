<?php

namespace App\Http\Controllers;

use App\Actions\SyncCategoriesAction;
use App\Exports\SupplierExport;
use App\Filters\SupplierFilter;
use App\Http\Requests\SupplierRequest;
use App\Imports\SupplierImport;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Services\StatementService;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SuppliersController extends Controller
{
    public function index(SupplierFilter $filter)
    {
        return inertia('Suppliers/Index', [
            'suppliers' => Supplier::filter($filter)
                ->when(request('sort_by'), function ($query, $sortBy) {
                    $query->orderBy(in_array($sortBy, ['name', 'created_at']) ? $sortBy : 'created_at', request('sort_order', 'desc'));
                }, function ($query) {
                    $query->latest();
                })
                ->with('categories')
                ->paginate(parent::ELEMENTS_PER_PAGE)
                ->withQueryString(),
            'categories' => Category::all(),
        ]);
    }

    public function store(SupplierRequest $request, SyncCategoriesAction $syncCategories)
    {
        $supplier = Supplier::create($request->all());

        $syncCategories->execute($supplier, $request->get('categories', []));

        return redirect()->route('suppliers.index')
            ->with('success', 'Supplier Created Successfully');
    }

    public function update(Supplier $supplier, SupplierRequest $request, SyncCategoriesAction $syncCategories)
    {
        $supplier->update($request->all());

        $syncCategories->execute($supplier, $request->get('categories', []));

        return back()->with('success', 'Supplier updated successfully');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return back()->with('success', 'Supplier Deleted successfully');
    }

    public function import()
    {
        Excel::import(new SupplierImport, request()->file('file'));

        return back()->with('success', 'Imported successfully');
    }

    public function importSample(): BinaryFileResponse
    {
        $filePath = storage_path('app/public/supplier_import_sample.csv');
        $headers = ['name', 'address', 'phone_number'];

        $handle = fopen($filePath, 'w');
        fputcsv($handle, $headers);
        fputcsv($handle, ['Example Supplier', 'Supplier Address 123', '0123456789']);
        fclose($handle);

        return response()->download($filePath)->deleteFileAfterSend();
    }

    public function export()
    {
        return Excel::download(new SupplierExport, 'suppliers.xlsx');
    }

    /**
     * Show supplier account with balance and invoices, payments and transactions.
     */
    public function account(Supplier $supplier)
    {
        $invoices = $supplier->invoices()
            ->latest()
            ->paginate(parent::ELEMENTS_PER_PAGE, ['*'], 'invoices_page')
            ->withQueryString();

        $payments = Payment::whereHas('invoice', function ($query) use ($supplier) {
            $query->where('invocable_id', $supplier->id)
                ->where('invocable_type', Supplier::class);
        })
            ->with('invoice')
            ->latest()
            ->paginate(parent::ELEMENTS_PER_PAGE, ['*'], 'payments_page')
            ->withQueryString();

        $transactions = Transaction::whereHas('invoice', function ($query) use ($supplier) {
            $query->where('invocable_id', $supplier->id)
                ->where('invocable_type', Supplier::class);
        })
            ->with(['product', 'invoice'])
            ->latest()
            ->paginate(parent::ELEMENTS_PER_PAGE, ['*'], 'transactions_page')
            ->withQueryString();

        return inertia('Suppliers/Account', [
            'supplier' => $supplier,
            'account_balance' => $supplier->account_balance,
            'invoices' => $invoices,
            'payments' => $payments,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Get supplier statement for a date range.
     */
    public function statement(Supplier $supplier)
    {
        $startDate = request('start_date', now()->subMonth());
        $endDate = request('end_date', now());

        $invoices = $supplier->invoices()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('payments')
            ->get();

        $payments = $supplier->getPaymentHistory()
            ->whereBetween('paid_at', [$startDate, $endDate]);

        return inertia('Suppliers/Statement', [
            'supplier' => $supplier,
            'invoices' => $invoices,
            'payments' => $payments,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'opening_balance' => $supplier->calculateAccountBalance($startDate),
        ]);
    }

    public function printStatement(Supplier $supplier, StatementService $statementService)
    {
        $startDate = request('start_date', now()->subMonth()->toDateTimeString());
        $endDate = request('end_date', now()->toDateTimeString());

        $pdf = $statementService->generatePdf($supplier, $startDate, $endDate);

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename='statement-{$supplier->name}.pdf'",
        ];

        return response(content: $pdf, headers: $headers);
    }
}
