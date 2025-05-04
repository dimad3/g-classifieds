<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Adverts\Advert\Advert;
use App\Models\Adverts\Advert\AttributeValue;
use App\Models\Adverts\Advert\Photo;
use App\Services\Adverts\CategoryAttributeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Class AdvertSeeder
 *
 * Seeds the adverts table with sample data, including attributes and photos for each advert.
 */
class AdvertSeeder1 extends Seeder
{
    protected CategoryAttributeService $categoryAttributeService;

    public function __construct(CategoryAttributeService $categoryAttributeService)
    {
        $this->categoryAttributeService = $categoryAttributeService;
    }

    /**
     * Run the database seeds.
     *
     * This method creates advert records and assigns attributes and photos to each advert.
     * It utilizes Faker for generating random data and performs additional logic
     * to populate related models such as `AttributeValue` and `Photo`.
     */
    public function run(): void
    {
        $faker = fake(); // Laravel's faker instance for generating random data
        // $photoDirectory = 'C:\laragon\www\ads2\public\storage\images_faker'; // Directory containing fake images
        $photoDirectory = storage_path(config('app.fake_images_directory')); // Directory containing fake images

        DB::transaction(function () use ($faker, $photoDirectory): void {
            // Create N adverts (where N is the count passed to the factory)
            Advert::factory(1000)->create()
                ->each(function (Advert $advert) use ($faker, $photoDirectory): void {
                    // Retrieve the category and action associated with the advert
                    $category = $advert->category;
                    $action = $advert->action;

                    $categoryAttributeService = new CategoryAttributeService();
                    // Retrieve available attributes for category and action
                    $availableAttributes = $categoryAttributeService->getAllAvailableAttributes($category, $action);
                    // Retrieve required attributes for category and action
                    $requiredAttributes = $categoryAttributeService->getRequiredAttributes($availableAttributes, $action);

                    // Store generated attribute values
                    $attributeValues = [];

                    // Generate values for available attributes
                    foreach ($availableAttributes as $attribute) {
                        $isRequired = $requiredAttributes->contains($attribute); // Check if the attribute is required
                        $createForOptional = $faker->boolean(80); // 80% chance to generate a value for optional attributes

                        // If the attribute is required or is optional but randomly chosen, generate a value
                        if ($isRequired || $createForOptional) {
                            // Generate attribute value based on its type
                            $value = match (true) {
                                $attribute->isBoolean() => $faker->boolean(), // Random boolean value
                                $attribute->isSelect() => $faker->randomElement($attribute->options), // Random selection from options
                                $attribute->isJson() => json_encode(
                                    $faker->randomElements($attribute->options, rand(0, count($attribute->options))), // Random JSON array
                                    JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
                                ),
                                $attribute->isInteger() => rand(1, 100), // Random integer
                                $attribute->isPrice() => format_price((string) $faker->randomFloat(2, 10, 100000)), // Formatted price
                                $attribute->isFloat() => $faker->randomFloat(2, 0.01, 100), // Random float
                                default => rtrim($faker->sentence(rand(1, 3)), '.'), // Random short sentence
                            };

                            // Prepare attribute value for insertion
                            $attributeValues[] = [
                                'advert_id' => $advert->id,
                                'attribute_id' => $attribute->id,
                                'value' => $value,
                            ];
                        }
                    }

                    // Bulk insert generated attribute values
                    AttributeValue::insert($attributeValues);

                    // Handle photos for the advert
                    $availableFiles = File::files($photoDirectory); // List all available files in the photo directory

                    if (! empty($availableFiles)) {
                        $photoCount = rand(1, 5); // Randomly assign 1-5 photos to the advert
                        $photos = []; // Store photo data

                        // Extract filenames from SplFileInfo objects
                        $availableFileNames = array_map(fn ($file) => $file->getFilename(), $availableFiles);

                        // Use Faker's unique() method to ensure unique file selection for this advert
                        $faker->unique(true); // Reset uniqueness for every advert

                        for ($i = 0; $i < $photoCount; $i++) {
                            if (count($availableFileNames) < $photoCount) {
                                break; // Stop if fewer files than required photos are available
                            }

                            // Select a unique random filename
                            $randomFileName = $faker->unique()->randomElement($availableFileNames);

                            // Add the photo record
                            $photos[] = [
                                'advert_id' => $advert->id,
                                'file' => 'images_faker/' . $randomFileName, // File path for the photo
                                'status' => Photo::STATUS_ACTIVE, // Set photo status to active
                            ];
                        }

                        // Bulk insert generated photos
                        Photo::insert($photos);
                    }
                });
        });
    }
}
