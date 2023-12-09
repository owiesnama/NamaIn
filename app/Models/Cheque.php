<?php

namespace App\Models;

use App\Enums\ChequeStatus;
use App\Filters\Filter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cheque extends BaseModel
{
    use SoftDeletes;

    /**
     * The accessors to append to the cheque in array form
     *
     * @var array<string>
     */
    public $appends = ['is_credit', 'amount_formated', 'due_for_humans'];

    /**
     * The attributes to cast along with casts.
     *
     * @var array<string,string>
     */
    public $casts = [
        'due' => 'datetime',
        'status' => ChequeStatus::class,
    ];

    /**
     * Searchable relations attribute of this cheque.
     *
     * @var array<string>
     */
    protected array $searchableRelationsAttributes = [
        'payee.name',
    ];

    /**
     * Check whether it's a credit cheque or debit check.
     */
    public function isCredit(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->type === 1
        );
    }

    /**
     * The amount of this cheque formatted.
     */
    public function amountFormated(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->amount, 2).' SDG'
        );
    }

    /**
     * The due of this cheque formatted on a readable way.
     */
    public function dueForHumans(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->due->diffForHumans()
        );
    }

    /**
     * The payee of this cheque.
     */
    public function payee(): MorphTo
    {
        return $this->morphTo('payee', 'chequeable_type', 'chequeable_id');
    }

    /**
     * Scope for filtering cheque query with a given filter.
     */
    public function scopeFilterUsing(Builder $query, Filter $filter): Builder
    {
        return $filter->apply($query);
    }

    /**
     * Set the payee attributes on the cheque.
     */
    public static function forPayee(array $attributes): Cheque
    {
        $cheque = new Cheque;
        $cheque->chequeable_id = $attributes['id'];
        $cheque->chequeable_type = $attributes['type'];

        return $cheque;
    }

    /**
     * Register cheque attributes and save it to DB.
     *
     * @return $this
     */
    public function register(array $attributes): Cheque
    {
        $this->amount = $attributes['amount'];
        $this->type = $attributes['type'];
        $this->due = Carbon::parse($attributes['due']);
        $this->bank = $attributes['bank'];
        $this->save();

        return $this;
    }
}
