<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Adverts\Category;
use Illuminate\Database\Seeder;

class AdvertCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Creating 12 Root Category instances using the Category factory
        Category::factory(12)
            ->create()
            ->each(function (Category $category): void {
                // Define the possible number of children for the current category:
                // Either 0 or a random number between 3 and 7
                $counts = [0, random_int(3, 7)];

                // Create child categories for the current category using the factory
                // The number of child categories is chosen randomly from the $counts array
                // The children categories are then saved to the current category
                $category->children()
                    ->saveMany(Category::factory($counts[array_rand($counts)])
                        ->create()
                        ->each(function (Category $category): void {
                            // Define a new $counts array for the children of the current child category
                            $counts = [0, random_int(3, 7)];

                            // Create further children for this child category, again using a random number of children
                            $category->children()
                                ->saveMany(Category::factory($counts[array_rand($counts)])
                                    ->create());
                        }));
            });
    }
}
