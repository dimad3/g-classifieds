<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Adverts\Advert\Advert;
use App\Models\Adverts\Advert\AttributeValue;
use App\Models\Adverts\Advert\Photo;
use App\Services\Adverts\CategoryAttributeService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AdvertSeeder2 extends Seeder
{
    protected CategoryAttributeService $categoryAttributeService;

    public function __construct(CategoryAttributeService $categoryAttributeService)
    {
        $this->categoryAttributeService = $categoryAttributeService;
    }

    public function run(): void
    {
        $faker = fake();
        $initialId = (int) DB::table('advert_adverts')->max('id') + 1 ?? 1;
        $photoDirectory = storage_path(config('app.fake_images_directory'));
        // $advertsCount = 0;

        // DB::transaction(function () use ($faker, $initialId, $photoDirectory, &$advertsCount) {
        DB::transaction(function () use ($faker, $initialId, $photoDirectory): void {
            // Prepare advert data
            $advertData = Advert::factory(5000)->make()
                ->map(function ($advert, $index) use ($initialId) {
                    $advert->id = $initialId + $index;
                    // $advert->created_at = Carbon::now()->toDateTimeString(); // Manually setting created_at
                    // $advert->updated_at = Carbon::now()->toDateTimeString(); // Manually setting updated_at

                    // Return the modified advert model
                    return $advert;
                });

            // $advertsCount = $advertData->count();

            // Insert adverts with manual IDs
            DB::table('advert_adverts')->insert($advertData->toArray());

            // Step 3: Generate `attribute_values` for each advert
            $attributeValues = [];
            foreach ($advertData as $advert) {
                $category = $advert->category;
                $action = $advert->action;

                $categoryAttributeService = new CategoryAttributeService();
                // Retrieve available attributes for category and action
                $availableAttributes = $categoryAttributeService->getAllAvailableAttributes($category, $action);
                // Retrieve required attributes for category and action
                $requiredAttributes = $categoryAttributeService->getRequiredAttributes($availableAttributes, $action);

                // Generate values for available attributes
                foreach ($availableAttributes as $attribute) {
                    $isRequired = $requiredAttributes->contains($attribute); // Check if the attribute is required
                    $createForOptional = $faker->boolean(80); // 80% chance to generate a value for optional attributes

                    // If the attribute is required or is optional but randomly chosen, generate a value
                    if ($isRequired || $createForOptional) {
                        // Generate attribute value based on its type
                        $value = match (true) {
                            $attribute->isBoolean() => $faker->boolean(),
                            $attribute->isSelect() => $faker->randomElement($attribute->options),
                            $attribute->isJson() => json_encode(
                                $faker->randomElements($attribute->options, rand(0, count($attribute->options))),
                                JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
                            ),
                            $attribute->isInteger() => rand(1, 100),
                            $attribute->isPrice() => format_price((string) $faker->randomFloat(2, 10, 100000)),
                            $attribute->isFloat() => $faker->randomFloat(2, 0.01, 100),
                            default => rtrim($faker->sentence(rand(1, 3)), '.'),
                        };

                        // Prepare attribute value for bulk insert
                        $attributeValues[] = [
                            'advert_id' => $advert->id,
                            'attribute_id' => $attribute->id,
                            'value' => $value,
                        ];
                    }
                }
            }

            // Bulk insert `attribute_values` into `advert_attribute_values` table
            AttributeValue::insert($attributeValues);

            // Step 4: Generate `photos` for each advert
            $availableFiles = File::files($photoDirectory); // List all available files in the photo directory
            $photos = [];

            // For each advert, assign random photos
            foreach ($advertData as $advert) {
                $photoCount = rand(1, 5); // Randomly assign 1-5 photos to the advert

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

                    // Prepare photo record for bulk insert
                    $photos[] = [
                        'advert_id' => $advert->id, // Use the ID of the created advert
                        'file' => 'images_faker/' . $randomFileName,
                        'status' => Photo::STATUS_ACTIVE,
                    ];
                }
            }

            // Bulk insert photos into `advert_photos` table
            Photo::insert($photos);

            // Simulating an error
            // throw new \Exception("Something went wrong inside transaction");
        });
        // Simulating an error
        // throw new \Exception("Something went wrong outside transaction");

        // If the transaction was successful, reset the auto-increment
        $autoIncrement = (int) DB::table('advert_adverts')->max('id') + 1 ?? 1;
        dump(DB::statement('ALTER TABLE advert_adverts AUTO_INCREMENT = ' . ($autoIncrement)));
    }
}
