<?php

namespace App\Actions;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;

class SyncCategoriesAction
{
    /**
     * Sync categories for a given model.
     */
    public function execute(Model $model, array $categories): void
    {
        $categoryIds = collect($categories)->map(function ($category) {
            return Category::firstOrCreate(
                ['id' => isset($category['id']) && is_numeric($category['id']) ? $category['id'] : null],
                ['name' => $category['name']]
            )->id;
        });

        $model->categories()->sync($categoryIds);
    }
}
