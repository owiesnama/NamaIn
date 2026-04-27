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
        return static::pluck('value', 'key');
    }
}
