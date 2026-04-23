<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Support\Facades\Storage;

class ExpenseReceiptController extends Controller
{
    /**
     * Stream the receipt file.
     */
    public function show(Expense $expense)
    {
        if (! $expense->receipt_path || ! Storage::disk('local')->exists($expense->receipt_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($expense->receipt_path);
    }
}
