<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseStatus;
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
        $expense->approve(ExpenseStatus::from($request->validated('status')));

        return back()->with('success', 'Expense '.$request->validated('status').' successfully');
    }
}
