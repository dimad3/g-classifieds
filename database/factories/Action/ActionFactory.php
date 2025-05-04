<?php

declare(strict_types=1);

namespace Database\Factories\Action;

use App\Models\Action\Action;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ActionFactory extends Factory
{
    protected $model = Action::class;

    public function definition()
    {
        // Generate a sentence with either 1 or 2 words, without a trailing period
        $sentence = rtrim($this->faker->unique()->sentence(rand(1, 3), false), '.');

        // Trim the sentence to 16 characters (if necessary)
        $name = substr($sentence, 0, 16);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
