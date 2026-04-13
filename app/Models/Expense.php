<?php

namespace App\Models;

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

    protected $fillable = [
        'title',
        'amount',
        'currency',
        'notes',
        'expensed_at',
        'created_by',
    ];

    /**
     * List of attributes to cast.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'expensed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Expense $expense) {
            $expense->currency = $expense->currency ?? preference('currency', 'USD');
            $expense->created_by = $expense->created_by ?? auth()->id();
        });
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
}
