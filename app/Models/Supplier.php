<?php

namespace App\Models;

use App\Traits\HasAccountBalance;
use App\Traits\HasPaymentHistory;
use App\Traits\WithTrashScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends BaseModel
{
    use HasAccountBalance,
        HasFactory,
        HasPaymentHistory,
        SoftDeletes,
        WithTrashScope;

    /**
     * List of attributes that can be mass assigned.
     *
     * @var array<string>
     */
    protected array $searchable = ['name', 'phone_number', 'address'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
        ];
    }

    /**
     * List of attributes to append.
     *
     * @var array<string>
     */
    protected $appends = ['created_at_human'];

    /**
     * Cheques wrote to this supplier.
     */
    public function cheques(): MorphMany
    {
        return $this->morphMany(Cheque::class, 'chequeable');
    }

    /**
     * Invoices issued for this supplier
     */
    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'invocable');
    }

    /**
     * The categories that belong to this supplier.
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    /**
     * The payments that belong to this supplier.
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Payments going OUT settle a supplier's balance (money we pay them).
     */
    protected function settlingDirection(): string
    {
        return 'out';
    }
}
