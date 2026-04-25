<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Traits\WithTrashScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends BaseModel
{
    use HasFactory, SoftDeletes, WithTrashScope;

    /**
     * Attributes that can be mass assigned.
     *
     * @var array<string>
     */
    protected array $searchable = ['title', 'notes', 'currency'];

    /**
     * List of attributes to cast.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'expensed_at' => 'datetime',
        'status' => ExpenseStatus::class,
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Expense $expense) {
            $expense->currency = $expense->currency ?? preference('currency', 'SDG');
            $expense->created_by = $expense->created_by ?? auth()->id();
        });
    }

    /**
     * The treasury account this expense was paid from.
     */
    public function treasuryAccount(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class);
    }

    /**
     * The categories that belongs to this expense.
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    /**
     * The user who created this expense.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The recurring expense that generated this expense.
     */
    public function recurringExpense(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class, 'recurring_expense_id');
    }

    /**
     * The user who approved or rejected this expense.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if the expense is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === ExpenseStatus::Approved;
    }

    /**
     * Check if the expense is pending.
     */
    public function isPending(): bool
    {
        return $this->status === ExpenseStatus::Pending;
    }

    /**
     * Update the status of the expense.
     */
    public function approve(ExpenseStatus $status): bool
    {
        return $this->update([
            'status' => $status,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
    }
}
