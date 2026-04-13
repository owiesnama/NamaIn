<?php

namespace App\Http\Controllers;

use App\Exports\CustomerExport;
use App\Http\Requests\CustomerRequest;
use App\Imports\CustomerImport;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Transaction;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomersController extends Controller
{
    public function index()
    {
        return inertia('Customers/Index', [
            'customers' => Customer::search(request('search'))
                ->trash(request('status'))
                ->when(request('category'), function ($query, $category) {
                    $query->whereHas('categories', function ($query) use ($category) {
                        $query->where('categories.id', $category);
                    });
                })
                ->when(request('sort_by'), function ($query, $sortBy) {
                    $query->orderBy(in_array($sortBy, ['name', 'created_at', 'credit_limit']) ? $sortBy : 'created_at', request('sort_order', 'desc'));
                }, function ($query) {
                    $query->latest();
                })
                ->with('categories')
                ->paginate(parent::ELEMENTS_PER_PAGE)
                ->withQueryString(),
            'categories' => Category::all(),
        ]);
    }

    public function store(CustomerRequest $request)
    {
        $customer = Customer::create($request->all());

        $categoryIds = collect($request->get('categories'))->map(function ($category) {
            return Category::firstOrCreate(
                ['id' => is_numeric($category['id']) ? $category['id'] : null],
                ['name' => $category['name']]
            )->id;
        });
        $customer->categories()->sync($categoryIds);

        return redirect()->route('customers.index')
            ->with('success', 'Customer Created Successfully');
    }

    public function update(Customer $customer, CustomerRequest $request)
    {
        $customer->update($request->all());

        $categoryIds = collect($request->get('categories'))->map(function ($category) {
            return Category::firstOrCreate(
                ['id' => is_numeric($category['id']) ? $category['id'] : null],
                ['name' => $category['name']]
            )->id;
        });
        $customer->categories()->sync($categoryIds);

        return back()->with('success', 'customer updated successfully');
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);
        $customer->delete();

        return back()->with('success', 'Storage Deleted successfully');
    }

    /**
     * Show customer account with balance and invoices, payments and transactions.
     */
    public function account(Customer $customer)
    {
        $invoices = $customer->invoices()
            ->latest()
            ->paginate(parent::ELEMENTS_PER_PAGE, ['*'], 'invoices_page')
            ->withQueryString();

        $payments = Payment::whereHas('invoice', function ($query) use ($customer) {
            $query->where('invocable_id', $customer->id)
                ->where('invocable_type', Customer::class);
        })
            ->with('invoice')
            ->latest()
            ->paginate(parent::ELEMENTS_PER_PAGE, ['*'], 'payments_page')
            ->withQueryString();

        $transactions = Transaction::whereHas('invoice', function ($query) use ($customer) {
            $query->where('invocable_id', $customer->id)
                ->where('invocable_type', Customer::class);
        })
            ->with(['product', 'invoice'])
            ->latest()
            ->paginate(parent::ELEMENTS_PER_PAGE, ['*'], 'transactions_page')
            ->withQueryString();

        return inertia('Customers/Account', [
            'customer' => $customer,
            'account_balance' => $customer->account_balance,
            'invoices' => $invoices,
            'payments' => $payments,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Get customer statement for a date range.
     */
    public function statement(Customer $customer)
    {
        $startDate = request('start_date', now()->subMonth());
        $endDate = request('end_date', now());

        $invoices = $customer->invoices()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('payments')
            ->get();

        $payments = $customer->getPaymentHistory()
            ->whereBetween('paid_at', [$startDate, $endDate]);

        return inertia('Customers/Statement', [
            'customer' => $customer,
            'invoices' => $invoices,
            'payments' => $payments,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'opening_balance' => $customer->calculateAccountBalance($startDate),
        ]);
    }

    public function printStatement(Customer $customer)
    {
        $startDate = request('start_date', now()->subMonth());
        $endDate = request('end_date', now());

        $invoices = $customer->invoices()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('payments')
            ->get();

        $payments = $customer->getPaymentHistory()
            ->whereBetween('paid_at', [$startDate, $endDate]);

        $opening_balance = $customer->calculateAccountBalance($startDate);

        $renderer = new ImageRenderer(
            new RendererStyle(80),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $qrCode = $writer->writeString(route('customers.statement', [$customer, 'start_date' => $startDate, 'end_date' => $endDate]));

        $pdf = Browsershot::html(
            view('print.statement', [
                'customer' => $customer,
                'invoices' => $invoices,
                'payments' => $payments,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'opening_balance' => $opening_balance,
                'qr' => $qrCode,
            ])->render()
        )->noSandbox()->format('A4')->pdf();

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename='statement-{$customer->name}.pdf'",
        ];

        return response(content: $pdf, headers: $headers);
    }

    public function import()
    {
        Excel::import(new CustomerImport, request()->file('file'));

        return back()->with('success', 'Imported successfully');
    }

    public function importSample(): BinaryFileResponse
    {
        $filePath = storage_path('app/public/customer_import_sample.csv');
        $headers = ['name', 'address', 'phone_number', 'credit_limit'];

        $handle = fopen($filePath, 'w');
        fputcsv($handle, $headers);
        fputcsv($handle, ['Example Customer', 'Customer Address 123', '0123456789', '5000']);
        fclose($handle);

        return response()->download($filePath)->deleteFileAfterSend();
    }

    public function export()
    {
        return Excel::download(new CustomerExport, 'customers.xlsx');
    }
}
