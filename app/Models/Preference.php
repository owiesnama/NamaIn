<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Preference extends Model
{
    /**
     * List of the attributes that can be mass assigned.
     *
     * @var array<string>
     */
    protected $fillable = ['key', 'value'];

    /**
     * Get the preferences as pairs.
     *
     * @return Collection<string,string|null>
     */
    public static function asPairs(): Collection
    {
        $preferences = Preference::all();

        return $preferences->map(
            fn (Preference $preference): array => [$preference->key => $preference->value]
        )->
        mapWithKeys(fn ($preference) => $preference);
    }
}
