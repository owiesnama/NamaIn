<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringExpense extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'amount',
        'currency',
        'notes',
        'frequency',
        'starts_at',
        'ends_at',
        'last_generated_at',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'last_generated_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (RecurringExpense $recurringExpense) {
            $recurringExpense->currency = $recurringExpense->currency ?? preference('currency', 'USD');
            $recurringExpense->created_by = $recurringExpense->created_by ?? auth()->id();
        });
    }

    /**
     * The expenses generated from this template.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'recurring_expense_id');
    }

    /**
     * The categories that belongs to this recurring expense.
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    /**
     * The user who created this template.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if a new expense should be generated.
     */
    public function isDue(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->starts_at->isAfter($today)) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isBefore($today)) {
            return false;
        }

        $nextDate = $this->nextDueDate();

        return $nextDate->isSameDay($today) || $nextDate->isPast();
    }

    /**
     * Get the next date an expense should be generated.
     */
    public function nextDueDate(): Carbon
    {
        $lastDate = $this->last_generated_at ? $this->last_generated_at->copy()->startOfDay() : null;
        $startDate = $this->starts_at->copy()->startOfDay();

        if (! $lastDate) {
            return $startDate;
        }

        return match ($this->frequency) {
            'daily' => $lastDate->addDay(),
            'weekly' => $lastDate->addWeek(),
            'monthly' => $lastDate->addMonth(),
            'yearly' => $lastDate->addYear(),
        };
    }
}
