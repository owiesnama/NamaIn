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
            if (isset($category['id']) && is_numeric($category['id'])) {
                return Category::firstOrCreate(
                    ['id' => $category['id']],
                    ['name' => $category['name'], 'type' => $type]
                )->id;
            }

            return Category::firstOrCreate(
                ['name' => $category['name']],
                ['type' => $type]
            )->id;
        });

        $model->categories()->sync($categoryIds);
    }
}
