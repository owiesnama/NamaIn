<?php

namespace App\Models;

use Illuminate\Support\Collection;

class Preference extends BaseModel
{
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
