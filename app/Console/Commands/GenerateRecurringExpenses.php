<?php

namespace App\Console\Commands;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\RecurringExpense;
use Illuminate\Console\Command;

class GenerateRecurringExpenses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expenses:generate-recurring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate expenses from active recurring templates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $templates = RecurringExpense::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now()->startOfDay());
            })
            ->get();

        $count = 0;

        foreach ($templates as $template) {
            if ($template->isDue()) {
                $expense = Expense::create([
                    'title' => $template->title,
                    'amount' => $template->amount,
                    'currency' => $template->currency,
                    'notes' => $template->notes,
                    'status' => ExpenseStatus::Pending,
                    'recurring_expense_id' => $template->id,
                    'expensed_at' => now(),
                    'created_by' => $template->created_by,
                ]);

                // Sync categories
                $expense->categories()->sync($template->categories->pluck('id'));

                // Update template
                $template->update([
                    'last_generated_at' => now(),
                ]);

                $count++;
            }
        }

        $this->info("Generated {$count} recurring expenses.");
    }
}
