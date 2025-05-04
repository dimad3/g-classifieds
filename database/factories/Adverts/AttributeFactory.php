<?php

declare(strict_types=1);

namespace Database\Factories\Adverts;

use App\Models\Adverts\Attribute;
use App\Models\Adverts\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        // Get the types list from the AdvertAttribute model
        $types = Attribute::typesList();
        $type = array_rand($types);

        // Generate an array of random words if it is necessary
        $options = [];

        if ($type !== Attribute::TYPE_BOOLEAN) {
            $numberOfOptions = rand(3, 9); // Generate between 3 and 9 options
            if ($type === Attribute::TYPE_JSON) {
                $options[] = rtrim($this->faker->sentence(rand(1, 3), true), '.'); // Each option is 1 to 3 words
            }
            $hasOptions = $this->faker->boolean;
            if ($hasOptions) {
                for ($i = 0; $i < $numberOfOptions; $i++) {
                    if ($type === Attribute::TYPE_INTEGER) {
                        $options[] = rand(1, 1000);
                    } elseif ($type === Attribute::TYPE_FLOAT) {
                        $options[] = $this->faker->randomFloat(2, 0.01, 100);
                    } else {
                        $options[] = rtrim($this->faker->sentence(rand(1, 3), true), '.'); // Each option is 1 to 3 words
                    }
                }
            }
        }

        // Pass JSON as an Array: Since the options column is cast to
        // an array ('options' => 'array' in the model), you should pass
        // the array directly to Eloquent.
        // Let Laravel handle the conversion to JSON when saving the data.
        // $options2 = json_encode($options, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        return [
            'category_id' => Category::factory(), // Assuming you have a factory for Category
            'name' => $this->faker->word, // Generate a random word for the name
            'sort' => 200, // defailt value is 200
            'type' => $type, // This will assign random type
            'options' => $options, // This will assign $options if it is necessary
        ];
    }
}
