<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Preference extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value'];

    public static function asPairs()
    {
        return static::all()->transform(function ($preference) {
            return [$preference->key => $preference->value];
        })->mapWithKeys(fn ($i) => $i);
    }
}
