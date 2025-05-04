<?php

declare(strict_types=1);

namespace Database\Factories\Action;

use App\Models\Action\Action;
use App\Models\Action\ActionCategory;
use App\Models\Adverts\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActionCategoryFactory extends Factory
{
    protected $model = ActionCategory::class;

    public function definition(): array
    {
        $defaultSort = $this->faker->boolean(90);

        return [
            'action_id' => Action::factory(), // Assuming you have a factory for Action
            'category_id' => Category::factory(), // Assuming you have a factory for Category
            'sort' => $defaultSort ? 200 : $this->faker->numberBetween(1, 255),
            'excluded' => $this->faker->boolean(20), // chance Of Getting True 20%
        ];
    }
}
