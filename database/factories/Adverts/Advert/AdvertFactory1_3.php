<?php

declare(strict_types=1);

namespace Database\Factories\Adverts\Advert;

use App\Models\Adverts\Advert\Advert;
use App\Models\Adverts\Category;
use App\Models\Region;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdvertFactory1_3 extends Factory
{
    protected $model = Advert::class;

    public function definition()
    {
        // Get a random leaf category
        $category = Category::whereDoesntHave('children')->inRandomOrder()->first();

        // Retrieve the possible actions for the selected category
        $actions = $category->getAdjustedActions($category->ancestorsAndMe());

        // Get a random action id from the list of available actions
        $actionId = $actions->isNotEmpty() ? $actions->random()->id : null;

        // Get a random leaf region
        $region = Region::whereDoesntHave('children')->inRandomOrder()->first();

        // Return attributes for the Advert model
        return [
            'user_id' => User::inRandomOrder()->first()->id,
            'category_id' => $category->id,
            'action_id' => $actionId,
            'region_id' => $region->id,
            'title' => substr($this->faker->words(rand(3, 9), true), 0, 64),
            'content' => $this->faker->paragraphs(rand(1, 5), true),
            'status' => Advert::STATUS_ACTIVE,
            'reject_reason' => null,
            'published_at' => $this->faker->dateTimeThisMonth(),
            'expires_at' => $this->faker->dateTimeBetween('now', '+1 month'),
        ];
    }
}
