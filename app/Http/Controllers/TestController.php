<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Cabinet\Adverts\CreateController;
use App\Jobs\BulkIndexAdvertsInElasticsearchJob;
use App\Models\Action\Action;
use App\Models\Adverts\Advert\Advert;
use App\Models\Adverts\Advert\AttributeValue;
use App\Models\Adverts\Attribute;
use App\Models\Adverts\Category;
use App\Models\Region;
use App\Models\User\User;
use App\Services\Adverts\AdvertService;
use App\Services\Adverts\CategoryAttributeService;
use App\Services\Adverts\Elasticsearch\AdvertDocsIndexerService;
use App\Services\Adverts\PhotoService;
use App\Services\RegionService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TestController extends Controller
{
    protected CategoryAttributeService $categoryAttributeService;

    private $paths = [];

    public function __construct(CategoryAttributeService $categoryAttributeService)
    {
        $this->categoryAttributeService = $categoryAttributeService;
    }

    public function test(): void
    {
        $advert = Advert::find(198714);
        dump(Advert::STATUS_DRAFT, (bool) ! empty($advert->reject_reason), ! empty($advert->reject_reason), $advert->reject_reason);
        echo '<hr>';
        dump(($advert->status === Advert::STATUS_DRAFT && (bool) ! empty($advert->reject_reason)));
        echo '<hr>';
        dd($advert->id, $advert->isRejected());

        // $category = Category::find(751); // checked (1350 - ok, 751 - not ok) methods are not the same
        // ($inheritedAttributesExcluded = $category->inheritedAttributesExcluded);
        // foreach ($inheritedAttributesExcluded as $key => $attributeExcluded) {
        //     dump($attributeExcluded->id . ' - ' . $attributeExcluded->name);
        // }
        // echo '<hr>';

        // ($excludedAttributesForSelfAndAncestors = $category->excludedAttributesForSelfAndAncestors());
        // foreach ($excludedAttributesForSelfAndAncestors as $key => $attributeExcluded) {
        //     dump($attributeExcluded->id . ' - ' . $attributeExcluded->name);
        // }
        // echo '<hr>';

        // return 1;

        // $category = Category::find(1362); // checked (363 - ok; 1350 - ok; 751 - ok)
        // // dd($category->ancestors->loadMissing('inheritedAttributesExcluded'));
        // $action = Action::find(8);

        // ($parentAttributes = $this->categoryAttributeService->getParentAttributes($category));
        // foreach ($parentAttributes as $attribute) {
        //     dump('parentAttributes: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // ($ancestorsAndSelfAttributes = $this->categoryAttributeService->getAncestorsAndSelfAttributes($category));
        // foreach ($ancestorsAndSelfAttributes as $attribute) {
        //     dump('ancestorsAndSelfAttributes: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // ($ancestorsAttributes = $this->categoryAttributeService
        //     ->getAncestorsAttributes($category));
        // foreach ($ancestorsAttributes as $attribute) {
        //     dump('ancestorsAttributes: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // ($inheritedAttributesExcluded = $category->inheritedAttributesExcluded);
        // foreach ($inheritedAttributesExcluded as $attribute) {
        //     dump('inheritedAttributesExcluded: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // ($ancestorsAttributesExcluded = $this->categoryAttributeService
        //     ->getAncestorsAttributesExcluded($category));
        // foreach ($ancestorsAttributesExcluded as $attribute) {
        //     dump('ancestorsAttributesExcluded: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // ($ancestorsAndSelfAttributesExcluded = $this->categoryAttributeService
        //     ->getAncestorsAndSelfAttributesExcluded($category));
        // foreach ($ancestorsAndSelfAttributesExcluded as $attribute) {
        //     dump('ancestorsAndSelfAttributesExcluded: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // ($availableAncestorsAttributes = $this->categoryAttributeService
        //     ->getAvailableAncestorsAttributes($category));
        // foreach ($availableAncestorsAttributes as $attribute) {
        //     dump('availableAncestorsAttributes: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // ($availableAncestorsAndSelfAttributes = $this->categoryAttributeService
        //     ->getAvailableAncestorsAndSelfAttributes($category));
        // foreach ($availableAncestorsAndSelfAttributes as $attribute) {
        //     dump('availableAncestorsAndSelfAttributes: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // $allAvailableAttributes = $this->categoryAttributeService
        //     ->getAllAvailableAttributes($category, $action);
        // foreach ($allAvailableAttributes as $attribute) {
        //     dump('allAvailableAttributes: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // $allAttributesExcluded = $this->categoryAttributeService->getAllAttributesExcluded($category, $action);
        // foreach ($allAttributesExcluded as $attribute) {
        //     dump('allAttributesExcluded: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // $requiredAttributes = $this->categoryAttributeService
        //     ->getRequiredAttributes($availableAncestorsAndSelfAttributes, $action);
        // foreach ($requiredAttributes as $attribute) {
        //     dump('requiredAttributes: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // $columnAttributes = $this->categoryAttributeService
        //     ->getColumnAttributes($availableAncestorsAndSelfAttributes, $action);
        // foreach ($columnAttributes as $attribute) {
        //     dump('columnAttributes: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // $excludedAttributesForAction = $this->categoryAttributeService
        //     ->getExcludedAttributesForAction($availableAncestorsAndSelfAttributes, $action);
        // foreach ($excludedAttributesForAction as $attribute) {
        //     dump('excludedAttributesForAction: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // return 1;

        // $category = Category::find(751); // checked (363 - ok; 1350 - ok; 751 - ok)
        // $ancestors = $category->ancestors;
        // $allAncestorsAttributes = $category->ancestorsAttributes();
        // $action = Action::find(1);

        // ($allAttributes = $category->getAllAttributes());
        // foreach ($allAttributes as $attribute) {
        //     dump('allAttributes: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // ($inheritedAttributesExcluded = $category->inheritedAttributesExcluded);
        // foreach ($inheritedAttributesExcluded as $attribute) {
        //     dump('inheritedAttributesExcluded: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // ($excludedAttributesForAncestors = $category->excludedAttributesForAncestors());
        // foreach ($excludedAttributesForAncestors as $attribute) {
        //     dump('excludedAttributesForAncestors: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // ($excludedAttributesForSelfAndAncestors = $category->excludedAttributesForSelfAndAncestors());
        // foreach ($excludedAttributesForSelfAndAncestors as $attribute) {
        //     dump('excludedAttributesForSelfAndAncestors: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // // ($availableAncestorsAttributes = $category->availableAncestorsAttributes($ancestors, $allAncestorsAttributes));
        // ($availableAncestorsAttributes = $category->availableAncestorsAttributes());
        // foreach ($availableAncestorsAttributes as $attribute) {
        //     dump('availableAncestorsAttributes: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // $availableAttributes = $category->availableAttributes($action);
        // foreach ($availableAttributes as $attribute) {
        //     dump('availableAttributes: ' . $attribute->id . ' - ' . $attribute->name);
        // }
        // echo '<hr>';

        // return 1;

        // // availableAncestorsAttributes() vs availableAttributes()
        // $category = Category::find(751); // checked (363 - ok; 1350 - ok; 751 - ok) methods retrn the same result
        // $ancestors = $category->ancestors;
        // $allAncestorsAttributes = $category->ancestorsAttributes();
        // $action = Action::find(1);

        // ($availableAncestorsAttributes = $category->availableAncestorsAttributes($ancestors, $allAncestorsAttributes));
        // foreach ($availableAncestorsAttributes as $availableAttribute) {
        //     dump($availableAttribute->id . ' - ' . $availableAttribute->name);
        // }
        // echo '<hr>';

        // $availableAttributes = $category->availableAttributes($action);
        // foreach ($availableAttributes as $availableAttribute) {
        //     dump($availableAttribute->id . ' - ' . $availableAttribute->name);
        // }
        // echo '<hr>';

        // return 2;

        // ($ancestorsAttributes = $category->ancestorsAttributes());
        // foreach ($ancestorsAttributes as $ancestorsAttribute) {
        //     dump($ancestorsAttribute->id . ' - ' . $ancestorsAttribute->name);
        // }
        // echo '<hr>';

        // ($excludedAttributesForSelfAndAncestors = $category->excludedAttributesForSelfAndAncestors());
        // foreach ($excludedAttributesForSelfAndAncestors as $excludedAttributeForSelfAndAncestors) {
        //     dump($excludedAttributeForSelfAndAncestors->id . ' - ' . $excludedAttributeForSelfAndAncestors->name);
        // }
        // echo '<hr>';

        // // Retrieve attributes required for category and action
        // $allAttributes = $category->getAllAttributes();
        // // Exclude ancestors' attributes for the selected action
        // $excludedAttributesForAction = $category->excludedAttributes($allAttributes, $action);
        // $availableAttributes = $allAttributes->except($excludedAttributesForAction->modelKeys());
        // // Exclude ancestors' attributes for the category itself
        // ($availableAttributes1 = $allAttributes->except($category->excludedAttributesForSelfAndAncestors()->modelKeys()));
        // foreach ($availableAttributes1 as $key => $availableAttribute) {
        //     dump($availableAttribute->id . ' - ' . $availableAttribute->name);
        // }

        // return 3;

        // $attribute = Attribute::find(465);
        // dump($attribute->isSelect());
        // return 1;

        // dump(User::inRandomOrder()->value('id'));
        // dump(User::inRandomOrder()->value('id'));
        // dump(User::inRandomOrder()->value('id'));
        // dump(User::inRandomOrder()->value('id'));
        // dump(User::inRandomOrder()->value('id'));
        // dump(User::inRandomOrder()->value('id'));

        // ($attribute = Attribute::find(676));
        // ($attribute->isSelect());

        // $advertAttributes = Attribute::factory(100)->create();
        // dd($types = array_column($advertAttributes->toArray(), 'type'));

        // Attribute::factory()->count(5)->create();

        // $type = 'json';
        // $options = [];

        // if (in_array($type, ['string', 'json'])) {
        //     $numberOfOptions = rand(3, 9); // Generate between 1 and 5 options
        //     for ($i = 0; $i < $numberOfOptions; $i++) {
        //         $options[] = rtrim(fake()->sentence(rand(1, 3), true), '.'); // Each option is 1 to 3 words
        //     }
        // }
        // dump($options);
        // // Encode as JSON
        // $options2 = json_encode($options, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        // dump($options2);

        // $data = [
        //     'category_id' => 1161,
        //     'name' => fake()->word, // Generate a random word for the name
        //     'sort' => 200, // defailt value is 200
        //     'type' => $type, // This will assign random type
        //     'options' => $options2, // This will assign $options if it is necessary
        // ];

        // Attribute::insert($data);
        // return 1;

        // $attributes = Attribute::whereIn('type', ['string', 'json'])->get();
        // foreach ($attributes as $attribute) {
        //     dump($options = $attribute->options);
        // }

        // $advertDocsIndexerService = new AdvertDocsIndexerService();
        // ($advertsCollection = Advert::where('id', '>', 240000)
        //     // ->where('id', '<', 238770)
        //     ->get()); // 240000 indexed
        // // dump(($advertsCollection = Advert::where('id', '>', 238763)->get())->count());
        // dispatch(new BulkIndexAdvertsInElasticsearchJob($advertsCollection));
        // // dump(count($advertsArray = Advert::where('id', '>', 238763)->get()->toArray()));
        // // $advertDocsIndexerService->bulkIndexAdverts($adverts);
        // // dd($advertsCollection = arrayToCollection($advertsArray));
        // return 4;

        // dump(DB::table('INFORMATION_SCHEMA.TABLES')
        //     ->where('TABLE_SCHEMA', env('DB_DATABASE')) // Your database name
        //     ->where('TABLE_NAME', 'advert_adverts') // Replace with your table name
        //     ->value('AUTO_INCREMENT'));

        // dump($leafCategories = Category::whereDoesntHave('children')->get());

        // dump($leafCategories = Category::whereIsLeaf()->get());
        // foreach ($leafCategories as $key => $leafCategory) {
        //    dump($leafCategory->name);
        // }
        // dump($otherCategories = Category::whereHas('children')->get());
        // return 3;

        // $initialId = (int) DB::table('advert_adverts')->max('id') + 1 ?? 1;
        // $adverts = Advert::factory(10)->make();
        // dump($adverts);
        // $advertData = $adverts->map(function ($advert, $index) use ($initialId) {
        //     $advert->id = $initialId + $index;
        //     $advert->created_at = Carbon::now(); // Manually setting created_at
        //     $advert->updated_at = Carbon::now(); // Manually setting updated_at
        //     // Return the modified advert model
        //     return $advert;
        // });
        // dump($adverts);
        // dump($advertData);
        // return 2;

        // $adverts = Advert::factory(1000)->make(); // Generate adverts but don't save them yet

        // // Prepare bulk insert data for `advert_adverts`
        // $advertData = [];
        // foreach ($adverts as $advert) {
        //     // Create the advert data for bulk insert
        //     $advertData[] = [
        //         'user_id' => $advert->user_id,
        //         'category_id' => $advert->category_id,
        //         'action_id' => $advert->action_id,
        //         'region_id' => $advert->region_id,
        //         'title' => $advert->title,
        //         'content' => $advert->content,
        //         'status' => $advert->status,
        //         'reject_reason' => $advert->reject_reason,
        //         'published_at' => $advert->published_at,
        //         'expires_at' => $advert->expires_at,
        //         'created_at' => now(), // Manually setting created_at
        //         'updated_at' => now(), // Manually setting updated_at
        //     ];
        // }

        // // Bulk insert adverts into `advert_adverts` table
        // DB::table('advert_adverts')->insert($advertData);

        // Step 2: Retrieve inserted advert IDs using a query
        // $advertIds = DB::table('advert_adverts')->latest('id')->take(count($advertData))->pluck('id')->toArray();

        // // Step 3: Generate `attribute_values` for each advert
        // $attributeValues = [];
        // foreach ($adverts as $index => $advert) {
        //     $category = $advert->category;
        //     $action = $advert->action;

        //     // Get all attributes and process them based on category and action
        //     $allAttributes = $category->getAllAttributes(); // All attributes available for the category
        //     $requiredAttributes = $category->requiredAttributes($allAttributes, $action); // Required attributes for the category
        //     $excludedAttributesForAction = $category->excludedAttributes($allAttributes, $action); // Attributes excluded by action
        //     $availableAttributes = $allAttributes->except($excludedAttributesForAction->modelKeys()); // Filtered attributes
        //     $availableAttributes = $availableAttributes->except($category->excludedAttributesForSelfAndAncestors()->modelKeys());

        //     // Generate values for available attributes
        //     foreach ($availableAttributes as $attribute) {
        //         $isRequired = $requiredAttributes->contains($attribute); // Check if the attribute is required
        //         $createForOptional = fake()->boolean(80); // 80% chance to generate a value for optional attributes

        //         // If the attribute is required or is optional but randomly chosen, generate a value
        //         if ($isRequired || $createForOptional) {
        //             // Generate attribute value based on its type
        //             $value = match (true) {
        //                 $attribute->isBoolean() => fake()->boolean(),
        //                 $attribute->isSelect() => fake()->randomElement($attribute->options),
        //                 $attribute->isJson() => json_encode(
        //                     fake()->randomElements($attribute->options, rand(0, count($attribute->options))),
        //                     JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        //                 ),
        //                 $attribute->isInteger() => rand(1, 100),
        //                 $attribute->isPrice() => format_price((string) fake()->randomFloat(2, 10, 100000)),
        //                 $attribute->isFloat() => fake()->randomFloat(2, 0.01, 100),
        //                 default => rtrim(fake()->sentence(rand(1, 3)), '.'),
        //             };

        //             // Prepare attribute value for bulk insert
        //             $attributeValues[] = [
        //                 'advert_id' => $advertIds[$index], // Use the ID of the created advert
        //                 'attribute_id' => $attribute->id,
        //                 'value' => $value,
        //             ];
        //         }
        //     }
        // }

        // // Bulk insert `attribute_values` into `advert_attribute_values` table
        // // AttributeValue::insert($attributeValues);

        // dump($attributeValues);
        // return 1;

        // dd($photoDirectory = url('storage/images_faker')); // 403 Forbidden
        // dd($photoDirectory = url('storage/app/public/images_faker')); // 404 Not Found
        // dd($photoDirectory = url('public/storage/images_faker')); // 404 Not Found
        // dd($photoDirectory = storage_path('app/public/images_faker'));
        // dd($photoDirectory = public_path('storage/images_faker'));

        // dd(session()->get('auth'));
        // ($controller = new CreateController(new AdvertService(new PhotoService)));
        // $category = Category::find(3);
        // $controller->selectSubCategory($category);

        // 19.10.2024 - Format Price Values
        // dd($attributeValue = AttributeValue::where('attribute_id', 416)->first()->value);   // ok
        // dd($attributeValue = AttributeValue::where('attribute_id', 416)->first()->price);   // ok

        // 18.10.2024 - testing getActionsAdvertsCounts() method
        // ($category = Category::where('slug', 'dzivokli')->first())->name; // ok
        // ($region = Region::where('slug', 'bolderaja')->first())->name; // ok
        // ($advertController = new AdvertController);     // ok
        // dump($getActionsAdvertsCounts = $advertController->getActionsAdvertsCounts($category, $region));   // ok
        // return 1;

        // 16.10.2024 - testing getRegionsAdvertsCounts() method
        // ($regions = Region::whereIsRoot()->get());  // ok
        // ($category = Category::where('slug', 'dzivokli')->first()); // ok
        // ($advertController = new AdvertController);     // ok
        // dump($regionsWithCount = $advertController->getRegionsAdvertsCounts($category, $regions));   // ok
        // return 1;

        // 12.10.2024 - bugs fixing for setting regions paths
        // $regionService = new RegionService();
        // ($regionService->rebuildRegionsPaths());  // ok 21:00
        // Cache::forget('regionsPaths');
        // dd(Cache::get('regionsPaths')); // 21:45 ok
        // dd(Cache::get('region_path_734')); // 21:45 ok

        // dd(Region::find(712)->getPath());  // ok 20:55

        // ok
        // ($ancestors = Region::find(712)->ancestors()->get());
        // foreach ($ancestors as $region) {
        //     dump($region->name);
        // }

        // ok
        // ($regions = Region::ancestorsAndSelf(712)->sortBy('_lft'));
        // foreach ($regions as $region) {
        //     dump($region->name);
        // }

        // 08.10.2024
        //dd($region = Region::find(634)->getPath()); // ok - "riga/bolderaja"
        // dd(Cache::get('regionsPaths'));

        // 07.10.2024 - trying to add optional 'depth' field to regions table and do updating it after fixTree()
        // to get depth for all regions one query is executed
        // dump($regions = Region::withDepth()->get());
        // return;

        // ok - get count of fixTree() method -> count is equal of updated records count
        // DB::enableQueryLog();
        // // Call your fixTree method
        // Region::fixTree();
        // // Get the executed queries
        // $queries = DB::getQueryLog();
        // $queryCount = count($queries);
        // echo "Total Queries Executed: " . $queryCount;

        // ok
        // $regions = Region::withDepth()->get();
        // $regions->each(function ($region) {
        //     try {
        //         // Bypass the model and update the database directly
        //         DB::table('regions')->where('id', $region->id)->update(['depth' => $region->depth]);

        //         echo $region->name . ' depth directly updated to ' . $region->depth . PHP_EOL;
        //     } catch (Exception $e) {
        //         echo 'Error updating ' . $region->name . ': ' . $e->getMessage() . PHP_EOL;
        //     }
        // });

        // DB::enableQueryLog(); // Enable the query log
        // $regions = Region::withDepth()->get();
        // $regions->each(function ($region) {
        //     try {
        //         $region->forceFill(['depth' => $region->depth])->save();
        //         echo $region->name . ' depth forcefully updated to ' . $region->depth . PHP_EOL;
        //     } catch (Exception $e) {
        //         echo 'Error updating ' . $region->name . ': ' . $e->getMessage() . PHP_EOL;
        //     }
        // });
        // // Output the queries
        // dd(DB::getQueryLog());

        // ok
        // DB::table('regions')->where('id', 1)->update(['depth' => 10]);

        // not ok
        // $regions = Region::withDepth()->get();
        // $regions->each(function ($region) {
        //     try {
        //         // Forcefully update the depth field
        //         $region->forceFill(['depth' => $region->depth])->save();

        //         echo $region->name . ' depth forcefully updated to ' . $region->depth . PHP_EOL;
        //     } catch (Exception $e) {
        //         echo 'Error updating ' . $region->name . ': ' . $e->getMessage() . PHP_EOL;
        //     }
        // });

        // not ok
        // $regions = Region::withDepth()->get();
        // $regions->each(function ($region) {
        //     try {
        //         // Manually assign and save the depth value
        //         $region->depth = $region->depth;
        //         $saved = $region->save();

        //         if ($saved) {
        //             echo $region->name . ' depth successfully updated to ' . $region->depth . PHP_EOL;
        //         } else {
        //             echo 'Failed to save depth for ' . $region->name . PHP_EOL;
        //         }
        //     } catch (Exception $e) {
        //         echo 'Error saving ' . $region->name . ': ' . $e->getMessage() . PHP_EOL;
        //     }
        // });

        // ok
        // $regions = Region::withDepth()->get();
        // $regions->each(function ($region) {
        //     try {
        //         // Try to update the depth field
        //         $region->update(['depth' => $region->depth]);

        //         // Check if the update succeeded
        //         if ($region->wasChanged('depth')) {
        //             echo $region->name . ' depth successfully updated to ' . $region->depth . PHP_EOL;
        //         } else {
        //             echo 'Failed to update depth for ' . $region->name . PHP_EOL;
        //         }
        //     } catch (Exception $e) {
        //         // Log the error for further inspection
        //         echo 'Error updating ' . $region->name . ': ' . $e->getMessage() . PHP_EOL;
        //     }
        // });

        // ok
        // $region = Region::first();
        // $region->update(['depth' => 2]);

        // if ($region->wasChanged('depth')) {
        //     echo 'Depth updated successfully!';
        // } else {
        //     echo 'Failed to update depth.';
        // }

        // $regions = Region::withDepth()->get();
        // $regions->each(function ($region) {
        //     // Update the depth field in the database
        //     $region->update(['depth' => $region->depth]);

        //     // Optionally print out the name and depth
        //     echo $region->name . ' depth: ' . $region->depth . PHP_EOL;
        // });

        // $regions = Region::withDepth()->get();
        // $regions->each(function ($region) {
        //     echo $region->name . ' depth: ' . $region->depth . PHP_EOL;
        // });

        // Now update the depth field for all regions
        // Region::withDepth()->get()->each(function ($region) {
        //     dump($region->name, $region->depth);
        //     $region->update(['depth' => $region->depth]);
        //     dump($region->name, $region->depth);
        // });

        // dd(Region::count());

        // 29.09.2024
        // ($category = Category::find(2641)); // 1362 = dārglietas; 1356 = aproces; 2641 = Akumulatori
        // $ancestorsAttributes = $category->ancestorsAttributes();
        // // $allAncestorsAttributes = $category->getAllAttributes();
        // ($category->availableAncestorsAttributes($ancestorsAttributes)->toArray());
        // return 1;
    }

    // public function test()
    // {
    //     // dd('handling started');
    //     $this->rebuildRegionsPaths();
    // }

    // private function rebuildRegionsPaths(): void
    // {
    //     $roots = Region::whereIsRoot()->withDepth()->orderBy('sort')->orderBy('name')->get();
    //     // dd($roots);  // ok
    //     foreach ($roots as $region) {
    //         $this->setPaths($region);
    //     }

    //     Cache::forget('regionsPaths');
    //     Cache::put('regionsPaths', $this->paths);
    //     // dump("Parents' Paths were rebuilt");
    //     // dd(Cache::get('regionsPaths'));
    // }

    // private function setPaths(Region $region): void
    // {
    //     // dd($region); // ok
    //     $this->paths[] = ['id' => $region->id, 'text' => $region->getPath(), 'depth' => $region->depth];
    //     //  dd($this->paths);
    //     foreach ($region->children()->orderBy('sort')->orderBy('name')->get() as $child) {
    //         $this->setPaths($child);
    //     }
    // }
}
