<?php

namespace App\Http\Controllers\Expenses;

use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseApprovalRequest;
use App\Models\Expense;
use Illuminate\Http\RedirectResponse;

class ExpenseApprovalController extends Controller
{
    /**
     * Update the status of the expense.
     */
    public function update(ExpenseApprovalRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);
        $expense->approve(ExpenseStatus::from($request->validated('status')));

        return back()->with('success', 'Expense '.$request->validated('status').' successfully');
    }
}
