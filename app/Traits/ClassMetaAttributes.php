<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait ClassMetaAttributes
{
    /**
     * Initialize the trait
     */
    public function initializeClassMetaAttributes(): void
    {
        $this->appends = array_merge($this->appends, ['type', 'type_string']);
    }

    /**
     * Get the full class name of this object
     */
    public function type(): Attribute
    {
        return new Attribute(get: fn () => get_class($this));
    }

    /**
     * Get the short class name of this object
     */
    public function typeString(): Attribute
    {
        return new Attribute(get: fn () => class_basename($this));
    }
}
