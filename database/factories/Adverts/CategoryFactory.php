<?php

declare(strict_types=1);

namespace Database\Factories\Adverts;

use App\Models\Adverts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array The default attributes for the Root Category.
     */
    public function definition(): array
    {
        // Generate a unique name for the category using the Faker library.
        $name = substr(rtrim($this->faker->unique()->sentence(rand(1, 3)), '.'), 0, 64);

        // Create a URL-friendly slug from the category name by replacing spaces with hyphens.
        // The Str::of() method creates a fluent string instance that allows you
        // to chain multiple string manipulation methods easily
        $slug = Str::of($name)->slug('-');
        $defaultSort = $this->faker->boolean(90);

        // Return an array of default attributes for the Category model.
        return [
            'name' => $name,          // The name of the category.
            'slug' => $slug,          // The URL-friendly slug for the category.
            'sort' => $defaultSort ? 200 : $this->faker->numberBetween(1, 255),
            'parent_id' => null,      // The parent category ID, set to null for top-level categories.
        ];
    }

    /**
     * Define a relationship to create a specified number of attributes for the category.
     *
     * @param  int  $count  The number of attributes to create for the category. Default is 5.
     * @return Factory The factory instance with the defined relationship.
     */
    public function hasAttributes($count = 5)
    {
        // Use the `has` method to define a one-to-many relationship with the Attribute factory.
        // This will create the specified number of attributes associated with the category.
        return $this->has(Attribute::factory()->count($count), 'categoryAttributes');
    }
}
