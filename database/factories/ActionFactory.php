<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ActionFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        // Generate a unique name for the action using the Faker library.
        $name = substr(rtrim($this->faker->unique()->sentence(rand(1, 2)), '.'), 0, 16);
        $slug = Str::of($name)->slug('-');

        return [
            'name' => $name,
            'slug' => $slug,
        ];
    }
}
