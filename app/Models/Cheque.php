<?php

namespace App\Models;

use App\Enums\ChequeStatus;
use App\Enums\ChequeType;
use App\Filters\Filters;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cheque extends BaseModel
{
    use HasFactory, SoftDeletes;

    /**
     * The accessors to append to the cheque in array form
     *
     * @var array<string>
     */
    public $appends = ['is_credit', 'amount_formated', 'due_formatted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due' => 'datetime',
            'status' => ChequeStatus::class,
            'type' => ChequeType::class,
        ];
    }

    /**
     * Searchable relations attribute of this cheque.
     *
     * @var array<string>
     */
    protected array $searchable = ['bank', 'reference_number', 'status'];

    protected array $searchableRelationsAttributes = [
        'payee.name',
    ];

    /**
     * Check whether it's a credit cheque or debit check.
     */
    public function getIsCreditAttribute(): bool
    {
        return $this->type === ChequeType::Receivable;
    }

    /**
     * The amount of this cheque formatted.
     */
    public function getAmountFormatedAttribute(): string
    {
        return number_format($this->amount, 2).' '.(preference('currency', 'SDG'));
    }

    /**
     * The due of this cheque formatted as Y-m-d.
     */
    public function getDueFormattedAttribute(): string
    {
        return $this->due->format('Y-m-d');
    }

    /**
     * The payee of this cheque.
     */
    public function payee(): MorphTo
    {
        return $this->morphTo('payee', 'chequeable_type', 'chequeable_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function scopeReceivable(Builder $query): Builder
    {
        return $query->where('type', ChequeType::Receivable);
    }

    public function scopePayable(Builder $query): Builder
    {
        return $query->where('type', ChequeType::Payable);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereDate('due', '<', now())
            ->whereNotIn('status', [ChequeStatus::Cleared, ChequeStatus::Cancelled]);
    }

    public function isReceivable(): bool
    {
        return $this->type === ChequeType::Receivable;
    }

    public function isPayable(): bool
    {
        return $this->type === ChequeType::Payable;
    }

    public function isEditable(): bool
    {
        return $this->status === ChequeStatus::Drafted;
    }

    /**
     * Scope for filtering cheque query with a given filter.
     */
    public function scopeFilter(Builder $query, Filters $filter): Builder
    {
        return $filter->apply($query);
    }

    protected static function booted(): void
    {
        parent::booted();

        static::saving(function (Cheque $cheque) {
            if ($cheque->bank_id && (! $cheque->bank || $cheque->isDirty('bank_id'))) {
                $cheque->bank = Bank::find($cheque->bank_id)?->name ?? 'Unknown';
            }
        });
    }

    /**
     * Set the payee attributes on the cheque.
     */
    public static function forPayee(int $id, string $type): Cheque
    {
        $cheque = new Cheque;
        $cheque->chequeable_id = $id;
        $cheque->chequeable_type = $type;

        return $cheque;
    }

    /**
     * Register cheque attributes and save it to DB.
     *
     * @return $this
     */
    public function register(array $attributes): Cheque
    {
        $this->fill([
            'amount' => $attributes['amount'],
            'type' => $attributes['type'],
            'due' => Carbon::parse($attributes['due']),
            'bank_id' => $attributes['bank_id'],
            'reference_number' => $attributes['reference_number'],
            'invoice_id' => $attributes['invoice_id'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'status' => ChequeStatus::Drafted,
        ])->save();

        return $this;
    }
}
