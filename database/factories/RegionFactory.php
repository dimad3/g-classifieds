<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RegionFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->city;
        $slug = Str::of($name)->slug('-');
        $defaultSort = $this->faker->boolean(90);

        return [
            'name' => $name,
            'slug' => $slug,
            'sort' => $defaultSort ? 200 : $this->faker->numberBetween(1, 255),
            'parent_id' => null,
        ];
    }
}
