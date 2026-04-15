<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Category extends BaseModel
{
    use HasFactory;

    protected static function booted(): void
    {
        static::unguard();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'budget_limit' => 'decimal:2',
        ];
    }

    /**
     * @var array<string>
     */
    protected $appends = ['created_at_human'];

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'categorizable');
    }

    public function expenses(): MorphToMany
    {
        return $this->morphedByMany(Expense::class, 'categorizable');
    }
}
