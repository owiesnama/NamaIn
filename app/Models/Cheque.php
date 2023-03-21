<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Cheque extends BaseModel
{
    use HasFactory;
    public $appends = ['is_credit', 'amount_formated', 'due_for_humans'];

    public $dates = ['due'];
    protected $searchableRelationsAttributes = [
        'payee.name',
    ];

    public function isCredit(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->type === 1
        );
    }

    public function amountFormated(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->amount, '2') . " SDG"
        );
    }

    public function dueForHumans(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->due->diffForHumans()
        );
    }

    public function payee(): MorphTo
    {
        return $this->morphTo('payee', 'chequeable_type', 'chequeable_id');
    }

    public static function forPayee($attributes)
    {
        $cheque = new static;
        $cheque->chequeable_id = $attributes['id'];
        $cheque->chequeable_type = $attributes['type'];

        return $cheque;
    }

    public function register($attributes)
    {
        $this->amount = $attributes['amount'];
        $this->type = $attributes['type'];
        $this->due = Carbon::parse($attributes['due']);
        $this->save();

        return $this;
    }
}
