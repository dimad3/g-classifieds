<?php

declare(strict_types=1);

namespace Database\Factories\Action;

use App\Models\Action\Action;
use App\Models\Action\ActionAttributeSetting;
use App\Models\Adverts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActionAttributeSettingFactory extends Factory
{
    protected $model = ActionAttributeSetting::class;

    public function definition(): array
    {
        $isRequired = $this->faker->boolean(80); // chance Of Getting True 80%
        $isExcluded = $this->faker->boolean(20); // chance Of Getting True 20%

        return [
            'action_id' => Action::factory(), // Assuming you have a factory for Action
            'attribute_id' => Attribute::factory(), // Assuming you have a factory for Attribute
            // 'required' => $this->faker->boolean(75), // chance Of Getting True 75%
            'required' => $isExcluded ? false : $isRequired,
            // 'column' => $this->faker->boolean(60), // chance Of Getting True 60%
            'column' => $isExcluded ? false : $this->faker->boolean(60), // chance Of Getting True 60%
            'excluded' => $isExcluded ? true : false,
        ];
    }
}
