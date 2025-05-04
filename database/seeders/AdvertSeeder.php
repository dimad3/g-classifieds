<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Jobs\BulkIndexAdvertsInElasticsearchJob;
use App\Models\Action\Action;
use App\Models\Adverts\Advert\Advert;
use App\Models\Adverts\Advert\Photo;
use App\Models\Adverts\Attribute;
use App\Models\Adverts\Category;
use App\Models\Region;
use App\Models\User\User;
use App\Services\Adverts\CategoryAttributeService;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Class AdvertSeeder
 *
 * This class is responsible for seeding the adverts into the database.
 * It generates adverts with associated attributes and photos, and
 * handles bulk insertion into the database.
 */
class AdvertSeeder extends Seeder
{
    protected $minAdvertsLimit = 1; // the minimum number of adverts that can be generated per category (not less than 1)

    protected $maxAdvertsLimit = 2; // the maximum number of adverts that can be generated per category

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check Elasticsearch before proceeding
        if (! is_elasticsearch_running()) {
            Log::error('Elasticsearch is not running. Seeder cannot proceed.');
            throw new \Exception('Elasticsearch is not running. Seeder failed.');
        }
        $startTime = microtime(true); // Start timing the execution
        $faker = fake(); // Create a Faker instance for generating fake data
        $photoDirectory = storage_path(config('app.fake_images_directory')); // Path to the fake photos directory
        $availableFiles = File::files($photoDirectory); // Get available photo files

        try {
            DB::transaction(function () use ($faker, $availableFiles): void {
                dump('Transaction started.');

                $leafCategories = $this->getLeafCategories();
                dump("{$leafCategories->count()} leaf categories retrieved.");

                try {
                    $adverts = $this->generateAdverts($leafCategories, $faker, $availableFiles);
                    $advertsCount = count($adverts['adverts']);
                    dump("{$advertsCount} adverts generated.");
                } catch (\Exception $e) {
                    Log::error('Error in generateAdverts() method: ' . $e->getMessage());
                    throw $e; // Re-throw to ensure rollback.
                }

                // dd(array_slice($adverts['attributeValues'], 0, 10));
                $this->bulkInsert('advert_adverts', $adverts['adverts']);
                dump("{$advertsCount} adverts inserted.");

                $this->bulkInsert('advert_attribute_values', $adverts['attributeValues']);
                $attributeValuesCount = count($adverts['attributeValues']);
                dump("{$attributeValuesCount} attributes inserted.");

                $this->bulkInsert('advert_photos', $adverts['photos']);
                $photosCount = count($adverts['photos']);
                dump("{$photosCount} photos inserted.");

                DB::afterCommit(function () use ($adverts): void {
                    dump('Job for Adverts bulk indexing in Elasticsearch is dispatched.');
                    dispatch(new BulkIndexAdvertsInElasticsearchJob($adverts['adverts']));
                });
            });
        } catch (\Exception $e) {
            Log::error('Transaction failed: ' . $e->getMessage());
        }

        $this->updateAutoIncrement();
        dump('Auto-increment updated.');

        $this->logExecutionTime($startTime); // Log the execution time
    }

    /**
     * Get all leaf categories from the database.
     *
     * @return Collection collection of categories without children
     */
    private function getLeafCategories(): Collection
    {
        return Category::whereDoesntHave('children')->get();
    }

    /**
     * Generate adverts based on categories, faker data, and available files.
     *
     * @param  int  $advertsLimit  the maximum number of adverts that can be generated per category
     */
    private function generateAdverts(Collection $categories, Generator $faker, array $availableFiles): array
    {
        $adverts = [];
        $attributeValues = [];
        $photos = [];
        $advertId = (int) DB::table('advert_adverts')->max('id') + 1 ?? 1; // Get the next advert ID
        $createdAt = now(); // Get the current timestamp

        foreach ($categories as $category) {
            $numAdverts = rand($this->minAdvertsLimit, $this->maxAdvertsLimit); // Randomly determine the number of adverts per category
            $regions = Region::whereDoesntHave('children')->inRandomOrder()->take($numAdverts)->get(); // Get random regions

            foreach (range(1, $numAdverts) as $index) {
                $region = $regions->random(); // Select a random region
                $action = $this->getActionForCategory($category); // Get action for the category

                // Create advert data and merge attributeValues and photos
                $adverts[] = $this->createAdvertData($advertId, $category, $region, $action, $faker, $createdAt);
                $attributeValues = array_merge($attributeValues, $this->generateAttributeValues($advertId, $category, $action, $faker));
                $photos = array_merge($photos, $this->generatePhotos($advertId, $faker, $availableFiles));

                $advertId++; // Increment the advert ID
            }
        }

        return compact('adverts', 'attributeValues', 'photos'); // Return generated adverts, attributeValues, and photos
    }

    /**
     * Get a random action for the given category.
     *
     * @param  Category  $category
     */
    private function getActionForCategory($category): ?Action
    {
        $actions = $category->getAdjustedActions($category->ancestorsAndMe()); // Get actions for the category

        return $actions->isNotEmpty() ? $actions->random() : null; // Return a random action if available
    }

    /**
     * Create advert data array.
     */
    private function createAdvertData(int $id, Category $category, Region $region, ?Action $action, Generator $faker, Carbon $createdAt): array
    {
        return [
            'id' => $id,
            'user_id' => User::inRandomOrder()->value('id'), // Random user ID
            'category_id' => $category->id,
            'action_id' => $action?->id,
            'region_id' => $region->id,
            'title' => substr(rtrim($faker->sentence(rand(3, 9)), '.'), 0, 64), // Generate a title
            'content' => $faker->paragraphs(rand(1, 5), true), // Generate content
            'status' => Advert::STATUS_ACTIVE,
            'reject_reason' => null,
            'published_at' => $faker->dateTimeThisMonth(), // Random published date
            'expires_at' => $faker->dateTimeBetween('now', '+1 month'), // Random expiration date
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /**
     * Generate attribute values for the advert.
     */
    private function generateAttributeValues(int $advertId, Category $category, ?Action $action, Generator $faker): array
    {
        $attributeValues = [];
        $categoryAttributeService = new CategoryAttributeService();
        // Retrieve available attributes for category and action
        $availableAttributes = $categoryAttributeService->getAllAvailableAttributes($category, $action);
        // Retrieve required attributes for category and action
        $requiredAttributes = $categoryAttributeService->getRequiredAttributes($availableAttributes, $action);

        foreach ($availableAttributes as $attribute) {
            // Determine if the attribute should be included
            if ($requiredAttributes->contains($attribute) || $faker->boolean(80)) {
                $value = $this->generateAttributeValue($attribute, $faker); // Generate attribute value
                $attributeValues[] = [
                    'advert_id' => $advertId,
                    'attribute_id' => $attribute->id,
                    'value' => $value,
                ];
            }
        }

        return $attributeValues; // Return generated attribute values
    }

    /**
     * Generate a value for a given attribute.
     *
     * @return mixed
     */
    private function generateAttributeValue(Attribute $attribute, Generator $faker)
    {
        return match (true) {
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
    }

    /**
     * Generate photos for the advert.
     *
     * @param  int  $advertId
     * @param Generator
     * @param  array  $availableFiles
     */
    private function generatePhotos($advertId, $faker, $availableFiles): array
    {
        $photos = [];
        $photoCount = rand(1, 10); // Randomly determine the number of photos

        if (count($availableFiles) < $photoCount) {
            return $photos; // Return empty if not enough files
        }

        $faker->unique(true); // Ensure unique filenames
        for ($i = 0; $i < $photoCount; $i++) {
            $randomFileName = $faker->unique()->randomElement(array_map(fn ($file) => $file->getFilename(), $availableFiles)); // Select a random file
            $photos[] = [
                'advert_id' => $advertId,
                'file' => 'images_faker/' . $randomFileName,
                'status' => Photo::STATUS_ACTIVE,
            ];
        }

        return $photos; // Return generated photos
    }

    /**
     * Bulk insert data into the specified table.
     *
     * @param  int  $chunkSize  chunk size for bulk insert
     */
    private function bulkInsert(string $table, array $data, int $chunkSize = 1000): void
    {
        $chunks = array_chunk($data, $chunkSize); // Split data into chunks

        foreach ($chunks as $chunk) {
            DB::table($table)->insert($chunk); // Insert each chunk into the database
        }
    }

    /**
     * Update the auto-increment value for the advert table.
     */
    private function updateAutoIncrement(): void
    {
        try {
            // Fetch max ID
            $newAutoIncrement = (int) DB::table('advert_adverts')->max('id') + 1 ?? 1;

            // Log the new auto-increment value
            // Log::debug('Calculated new auto-increment value:', ['value' => $newAutoIncrement]);

            // Execute the ALTER TABLE statement
            DB::statement("ALTER TABLE advert_adverts AUTO_INCREMENT = {$newAutoIncrement}");

            // Log successful update
            // Log::debug('Auto-increment updated successfully.');
        } catch (\Exception $e) {
            // Log errors
            Log::error('Error in updateAutoIncrement() method:', ['message' => $e->getMessage()]);
            throw $e; // Re-throw exception to prevent silent failure
        }
    }

    /**
     * Log the execution time of the seeder.
     */
    private function logExecutionTime(float $startTime): void
    {
        $endTime = microtime(true); // Get the end time
        $executionTime = $endTime - $startTime; // Calculate execution time
        dump(sprintf('AdvertSeeder executed in %.2f seconds.', $executionTime)); // Log execution time
    }
}
