<?php

namespace App\Actions;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;

class SyncCategoriesAction
{
    /**
     * Sync categories for a given model.
     */
    public function execute(Model $model, array $categories, ?string $type = null): void
    {
        $type = $type ?? strtolower(class_basename($model));

        $categoryIds = collect($categories)->map(function ($category) use ($type) {
            return Category::firstOrCreate(
                ['id' => isset($category['id']) && is_numeric($category['id']) ? $category['id'] : null],
                ['name' => $category['name'], 'type' => $type]
            )->id;
        });

        $model->categories()->sync($categoryIds);
    }
}
