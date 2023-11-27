<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasClassAttributes
{
    /**
     * Initialize the trait
     *
     * @return void
     */
    public function initializeHasClassAttributes(): void
    {
        $this->appends = array_merge($this->appends, ['type', 'type_string']);
    }

    /**
     * Get the full class name of this object
     *
     * @return Attribute
     */
    public function type(): Attribute
    {
        return new Attribute(get: fn() => get_class($this));
    }

    /**
     * Get the short class name of this object
     *
     * @return Attribute
     */
    public function typeString(): Attribute
    {
        return new Attribute(get: fn() => class_basename($this));
    }
}
