<?php

namespace App\Traits;


use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait WithTrashScope
{
    /**
     * Scope the query according to trash status.
     *
     * @param $builder
     * @param string|null $status
     * @return Model|Builder
     */
    public function scopeTrash($builder, string|null $status): Model|Builder
    {
        return match ($status) {
            'withTrash' => $builder->withTrashed(),
            'trash' => $builder->onlyTrashed(),
            default => $builder,
        };
    }
}
