<?php

declare(strict_types=1);

namespace Database\Factories\Adverts;

use App\Models\Adverts\Attribute;
use App\Models\Adverts\Category;
use App\Models\Adverts\CategoryInheritedAttributesExclusion;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryInheritedAttributesExclusionFactory extends Factory
{
    protected $model = CategoryInheritedAttributesExclusion::class;

    public function definition(): array
    {
        return [
            'attribute_id' => Attribute::factory(), // Assuming you have a factory for Attribute
            'category_id' => Category::factory(), // Assuming you have a factory for Category
        ];
    }
}
