<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\RecurringExpense;

class RecurringExpenseStatusController extends Controller
{
    public function update(RecurringExpense $recurringExpense)
    {
        $recurringExpense->update([
            'is_active' => ! $recurringExpense->is_active,
        ]);

        return back()->with('success', __('Recurring expense '.($recurringExpense->is_active ? 'activated' : 'paused').' successfully'));
    }
}
