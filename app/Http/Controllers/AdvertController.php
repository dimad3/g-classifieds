<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Router\AdvertsPath;
use App\Models\Action\Action;
use App\Models\Adverts\Advert\Advert;
use App\Models\Adverts\Advert\Dialog\Dialog;
use App\Models\Adverts\Category;
use App\Models\Region;
use App\Services\Adverts\CategoryAttributeService;
use App\Services\Adverts\Elasticsearch\SearchService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/*
|--------------------------------------------------------------------------
| AdvertController
|--------------------------------------------------------------------------
|
| This controller manages functionalities accessible to guests on the site.
| It enables users to browse a comprehensive list of adverts, as well as view
| detailed information about specific adverts.
|
| Key Responsibilities:
| - Display an index of adverts with relevant filtering options.
| - Provide detailed views for individual adverts, respecting access permissions.
| - Facilitate the management of advert categories and regions, enabling dynamic
|   data presentation.
|
*/

class AdvertController extends Controller
{
    protected CategoryAttributeService $categoryAttributeService;

    protected SearchService $searchService;

    public function __construct(CategoryAttributeService $categoryAttributeService, SearchService $searchService)
    {
        $this->categoryAttributeService = $categoryAttributeService;
        $this->searchService = $searchService;
    }

    /**
     * Display the adverts index page based on the selected category and region.
     *
     * This method determines whether to display a list of categories, regions,
     * actions, or adverts based on the category and region data extracted from the URL.
     *
     * The method fetches and processes relevant data such as categories, regions, actions,
     * and adverts with their column attributes to be displayed on the index page.
     *
     * @param  Request  $request  The HTTP request object that may contain filtering parameters, such as action.
     * @param  AdvertsPath  $path  The path object that holds the resolved category and region data parsed from the URL.
     * @return \Illuminate\View\View The view that displays either the categories list,
     *                               regions list, actions list, or adverts index based
     *                               on the current context.
     */
    public function index(Request $request, AdvertsPath $path)
    {
        // Retrieve the category and region from the resolved path (can be null if not provided).
        $category = $path->category;
        $region = $path->region;

        // Step 1: Attempt to display the category list.
        // Fetch and split the categories into chunks for multi-column rendering.
        $categoriesChunks = $this->getCategoriesChunks($category);

        // If categories are available, render the categories list and count adverts for each category.
        if ($categoriesChunks->isNotEmpty()) {
            // Flatten chunks to obtain a collection of all categories.
            $categories = $categoriesChunks->flatten();

            // Get the advert counts for all categories.
            $categoriesWithAdvertsCount = $this->getCategoriesAdvertsCounts($categories);

            // Assign advert counts to each category in the chunked collection.
            foreach ($categoriesChunks as $chunk) {
                foreach ($chunk as $cat) {
                    // Find the corresponding advert count for the category.
                    $categoryWithAdvertsCount = $categoriesWithAdvertsCount->firstWhere('id', $cat->id);
                    // Add advert_count to the category (set to 0 if none found).
                    $cat->adverts_count = $categoryWithAdvertsCount ? $categoryWithAdvertsCount->adverts_count : 0;
                }
            }

            // Return the view displaying the categories list.
            return view('adverts.categories_list', compact(
                'category',
                'region',
                'categoriesChunks',
            ));
        }

        // Step 2: If no categories are found, attempt to display the region list.
        // Fetch and split the regions into chunks for multi-column rendering.
        $regionsChunks = $this->getRegionsChunks($region);

        // If regions are available, render the regions list.
        if ($regionsChunks->isNotEmpty()) {
            // Flatten chunks to obtain a collection of all regions.
            $regions = $regionsChunks->flatten();

            // Get the advert counts for all categories.
            $regionsWithAdvertsCount = $this->getRegionsAdvertsCounts($category, $regions);

            // Assign advert counts to each regon in the chunked collection.
            foreach ($regionsChunks as $chunk) {
                foreach ($chunk as $reg) {
                    // Find the corresponding advert count for the category.
                    $regionWithAdvertsCount = $regionsWithAdvertsCount->firstWhere('id', $reg->id);
                    // Add advert_count to the category (set to 0 if none found).
                    $reg->adverts_count = $regionWithAdvertsCount ? $regionWithAdvertsCount->adverts_count : 0;
                }
            }

            // Return the view displaying the regions list.
            return view('adverts.regions_list', compact(
                'category',
                'region',
                'regionsChunks',
            ));
        }

        // Step 3: If neither categories nor regions are available, fetch actions and adverts.
        // Retrieve the available actions for the current category, along with the count of adverts for each action.
        // also getActionsAdvertsCounts() method can be used for counting adverts
        ($actions = $category->getAdjustedActions($category->ancestorsAndMe())
            ->loadCount(['adverts' => function (Builder $query) use ($category, $region): void {
                $query->where('status', '=', 'active') // Include only active adverts
                    ->where('expires_at', '>', now()) // Include only adverts that are not expired
                    ->where('category_id', $category->id) // Filter by the provided category
                    ->where('region_id', $region->id); // Filter by the provided region
            }]));

        // Determine the current action based on request parameters.
        $action = $this->getAction($request, $actions);

        // If actions are available and no specific action is set in the request parameters, display the actions list.
        if ($actions->isNotEmpty() && ! $action) {
            return view('adverts.actions_list', compact('category', 'region', 'actions'));
        }
        // todo: is it right condition ($actions->isNotEmpty() && !$action) to render actions list?

        // Step 4: Fetch adverts and attributes for the adverts table.
        ($adverts = $this->getAdverts($category, $region, $action)); // Get paginated adverts
        ($columnsAttributes = $this->getColumnAttributes($category, $action)); // Get table columns attributes

        // Return the view displaying the list of adverts and associated attributes.
        return view('adverts.index', compact('category', 'region', 'adverts', 'columnsAttributes'));
    }

    /**
     * Search for adverts using Elasticsearch.
     *
     * @return \Illuminate\View\View
     */
    public function search(Request $request)
    {
        try {
            if (! is_elasticsearch_running()) {
                // Check if the previous URL is 'adverts.search'
                // Trim query parameters from url()->previous() by using `before()` for urls comparison
                if (Str::before(url()->previous(), '?') == route('adverts.search')) {
                    // Redirect to 'home' if coming from 'adverts.search' to avoid infinite redirects
                    return redirect()->route('home')->with('error', 'Search service is currently unavailable. Please try again later.');
                }

                // Otherwise, proceed with the normal back redirect
                return back()->with('error', 'Search service is currently unavailable. Please try again later.');
            }

            // Validate the incoming request
            $validated = $request->validate([
                'search' => 'required|string|max:255', // Ensure search term is present and valid
                'page' => 'nullable|integer|min:1',   // Validate page if provided
            ]);

            $query = $validated['search']; // Extract the search query
            $page = $request->input('page', 1); // Default to page 1 if not provided
            $size = 20; // Number of results per page

            // Perform the search using the Elasticsearch service
            $response = $this->searchService->searchAdverts($query, $size, ($page - 1) * $size);

            $adverts = $response['hits']['hits']; // Get the search results
            $total = $response['hits']['total']['value'] ?? 0; // Get total hits

            // Process the search results
            $currentPageItems = array_map(function ($item) {
                return [
                    'id' => $item['_id'], // Retain ID
                    'highlight' => $item['highlight'] ?? [], // Retain highlights if available
                    ...$item['_source'], // Include other fields
                ];
            }, $adverts);

            // Prepare the paginator instance
            $advertsPaginator = new LengthAwarePaginator($currentPageItems, $total, $size, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);

            // Return the view with the paginated search results
            return view('adverts.search', [
                'adverts' => $advertsPaginator,
                'query' => $query,
            ]);
        } catch (Exception $e) {
            Log::error('General search error: ' . get_class($e) . " - {$e->getMessage()}");
            // Check if the previous URL is 'adverts.search'
            // For comparison trim query parameters from url()->previous() by using `before()`
            if (Str::before(url()->previous(), '?') == route('adverts.search')) {
                // Redirect to 'home' if coming from 'adverts.search' to avoid infinite redirects
                return redirect()->route('home')->with('error', 'An error occurred while processing your search. Please try again later.');
            }

            // Otherwise, proceed with the normal back redirect
            return back()->with('error', 'An error occurred while processing your search. Please try again later.');
        }
    }

    /**
     * Get the advert counts for a given collection of categories
     * (aggregated advert counts of each category's leaf descendants).
     *
     * @param  SupportCollection  $categories  A collection of Category models for which advert counts are to be retrieved.
     * @return SupportCollection|\stdClass[] A collection of objects, each containing:
     *                                       - id: The ID of the category (int)
     *                                       - name: The name of the category (string)
     *                                       - advert_count: The total count of adverts associated with this category (int).
     *
     * This method executes a single SQL query to aggregate the number of adverts for each specified category,
     * including its leaf descendants, efficiently using left joins.
     */
    public function getCategoriesAdvertsCounts(SupportCollection $categories): SupportCollection
    {
        // Step 1: Extract the IDs of the provided categories
        $categoriesIds = $categories->pluck('id');

        // Step 2: Fetch the advert counts for the provided categories
        /**
         * @var SupportCollection|\stdClass[] $categoriesWithAdvertCounts
         *
         * A collection of objects where each object represents a category and includes:
         * - id: The ID of the category (int)
         * - name: The name of the category (string)
         * - advert_count: The total count of adverts associated with this category (int).
         *
         * This collection is populated by executing a query that counts the number of adverts
         * for each category and its descendants, filtered by the provided categories.
         */
        $categoriesWithAdvertCounts = DB::table('advert_categories as parent')
            ->leftJoin('advert_categories as child', function ($join): void {
                // Perform a self-join to include child categories in the counting process
                // The join condition ensures that child categories fall within the range of the parent category
                $join->on('child._lft', '>=', 'parent._lft') // The left boundary of the child must be greater than or equal to the parent's left boundary
                    ->on('child._rgt', '<=', 'parent._rgt'); // The right boundary of the child must be less than or equal to the parent's right boundary
            })
            ->leftJoin('advert_adverts', 'advert_adverts.category_id', '=', 'child.id')
            // Join the adverts table to count the number of adverts linked to each child category
            ->select(
                'parent.id', // Select the parent category ID
                'parent.name', // Select the parent category name
                DB::raw('COUNT(advert_adverts.id) as adverts_count') // Count the number of adverts for the parent category and its descendants
            )
            ->whereIn('parent.id', $categoriesIds) // Filter by the IDs of the provided categories
            ->where('advert_adverts.status', '=', 'active') // Add condition for active adverts
            ->where('advert_adverts.expires_at', '>', now()) // Add condition for non-expired adverts
            ->groupBy('parent.id', 'parent.name') // Group the results by parent category ID and name
            ->get(); // Execute the query and retrieve the results as a collection

        return $categoriesWithAdvertCounts;
    }

    /**
     * Get the count of active adverts for each action based on the specified category and region.
     *
     * This method retrieves the counts of active, non-expired adverts grouped by action for
     * a given category and region. It joins the 'advert_adverts' and 'actions' tables, applies
     * necessary filtering conditions (category, region, active status, and expiry date), and
     * groups the results by action.
     *
     * @param  Category  $category  The category for which adverts are counted.
     * @param  Region  $region  The region for which adverts are counted.
     * @return SupportCollection A collection containing action IDs, action names, and the count of adverts for each action.
     */
    public function getActionsAdvertsCounts(Category $category, Region $region): SupportCollection
    {
        // Join the 'advert_adverts' and 'actions' tables and count the adverts per action
        $actionsWithAdvertCounts = DB::table('advert_adverts')
            ->join('actions', 'advert_adverts.action_id', '=', 'actions.id')
            ->select('action_id', 'actions.name', DB::raw('COUNT(*) as adverts_count')) // Select action details and count adverts
            ->where('category_id', $category->id) // Filter by the provided category
            ->where('region_id', $region->id) // Filter by the provided region
            ->where('status', '=', 'active') // Include only active adverts
            ->where('expires_at', '>', now()) // Include only adverts that are not expired
            ->groupBy('action_id') // Group by the action ID to get counts per action
            ->get();

        // Return the collection of action counts
        return $actionsWithAdvertCounts;
    }

    /**
     * Display the specified advert.
     *
     * This method handles the retrieval and display of a single advert. It first
     * checks whether the advert is active. If the advert is not active, only the
     * admin, moderator, or the advert author can view it. Otherwise, a 404 error
     * is triggered. If the user has the necessary permissions, the advert details
     * are fetched along with associated relations such as category, user, photos,
     * and attributes with values.
     *
     * Additional data like the authenticated user and related dialogs for clients
     * are also loaded to manage the display of buttons such as 'favorite' and
     * 'send message' in the view.
     *
     * @param  Advert  $advert  The advert model to display.
     * @return \Illuminate\View\View The view displaying the advert details.
     */
    public function show(Advert $advert)
    {
        // Only admin, moderator, or the advert's author can view inactive adverts.
        if (! ($advert->isActive() || Gate::allows('show-advert', $advert))) {
            abort(404);
        }

        // Load advert details along with its relationships
        $advert = $advert->loadMissing([
            // 'category:id,name', // commented on 18.10.2024: result is bug see breadcrumbs for adverts.show
            'category',
            'region',
            'user:id,name',
            'activePhotos',
            'attributesWithValues' => function ($query): void {
                // Load the attributes with values, ordering them by 'sort'
                $query->select('name', 'sort', 'type')
                    ->orderBy('sort');
            },
        ]);

        $user = Auth::user();  // Get the authenticated user for managing 'favorite' button

        /**
         * @var Dialog $dialog  Get the dialog where the authenticated user is a client.
         */
        $dialog = $advert->dialogWereUserIsClient;  // Manage 'send message' button, visible only for clients.

        // Return the 'adverts.show' view with the advert, user, and dialog data.
        return view('adverts.show', compact('advert', 'user', 'dialog'));
    }

    /**
     * Retrieve the phone number of the advert's author.
     *
     * This method returns the phone number of the user who posted the specified advert.
     * Before doing so, it checks if the advert is active. If the advert is not active,
     * only users with special permissions (admin, moderator, or the advert author) are
     * allowed to view the phone number. If the user does not meet these conditions,
     * a 404 error is triggered.
     *
     * @param  Advert  $advert  The advert model whose author's phone number is to be retrieved.
     * @return string The phone number of the advert's author.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException If the user does not have permission to view the phone number of an inactive advert.
     */
    public function phone(Advert $advert): string
    {
        // Only admin, moderator, or the advert's author can view phone numbers of inactive adverts.
        if (! ($advert->isActive() || Gate::allows('show-advert', $advert))) {
            abort(404);
        }

        // Return the phone number of the user who posted the advert.
        return $advert->user->phone;
    }

    /**
     * Get the categories chunks for display.
     *
     * This method retrieves categories based on the provided category parameter. If no category is provided,
     * it fetches and returns the root categories. If the given category has subcategories, it fetches them.
     * Otherwise, it returns an empty collection.
     *
     * The categories are split into chunks for easier rendering in multiple columns in the view.
     *
     * @param  Category|null  $category  The current category from the URL, or null if not provided.
     * @return EloquentCollection A collection of categories, split into chunks for display. If no category is provided,
     *                            root categories are returned. If the category has subcategories, they are returned.
     *                            An empty collection is returned if neither condition is met.
     */
    private function getCategoriesChunks(?Category $category): EloquentCollection
    {
        // If no category is selected, fetch root categories
        if (! $category) {
            $rootCategories = Category::whereIsRoot()->orderBy('sort')->orderBy('name')->get();

            // Split root categories into chunks for rendering in multiple columns
            return $this->splitCollectionByColumns($rootCategories);
        }

        // If the selected category has children (subcategories), fetch them
        if ($category->children()->exists()) {
            $subCategories = $category->children()
                ->select('id', 'name', 'slug', 'parent_id')
                ->orderBy('sort')
                ->orderBy('name')
                ->get();

            // Split subcategories into chunks for rendering in multiple columns
            return $this->splitCollectionByColumns($subCategories);
        }

        // Return an empty collection if no subcategories are found
        return new EloquentCollection();
    }

    /**
     * Get the regions chunks for display.
     *
     * This method retrieves regions and their associated adverts (filtered by category)
     * and splits them into chunks for rendering in the view.
     *
     * @param  Region|null  $region  The current region or null if none is selected.
     * @return EloquentCollection The collection of regions split into chunks for display.
     */
    private function getRegionsChunks(?Region $region): EloquentCollection
    {
        // If no region is provided, fetch root regions (top-level regions)
        if (! $region) {
            $rootRegions = Region::whereIsRoot()->orderBy('sort')->orderBy('name')->get();

            // Split root regions into chunks for rendering in multiple columns
            return $this->splitCollectionByColumns($rootRegions);
        }

        // If the provided region has subregions (child regions), fetch them
        if ($region->children()->exists()) {
            $regions = $region->children()
                ->select('id', 'name', 'slug', 'parent_id')
                ->orderBy('sort')
                ->orderBy('name')
                ->get();

            // Split subregions into chunks for rendering in multiple columns
            return $this->splitCollectionByColumns($regions);
        }

        // Return an empty collection if no subregions are found
        return new EloquentCollection();
    }

    /**
     * Retrieve a valid action based on the request parameters and the provided collection of actions.
     *
     * This method checks the 'action' parameter from the incoming HTTP request to ensure that it is
     * a valid numeric value and corresponds to one of the available action IDs in the given collection.
     * If the validation fails, a 404 error is triggered. If the validation passes, it fetches and
     * returns the corresponding Action model.
     *
     * @param  Request  $request  The HTTP request object containing parameters that may include the 'action' ID.
     * @param  EloquentCollection  $actions  A collection of Action objects that serves as the valid reference for action IDs.
     * @return Action|null Returns the Action model if a valid 'action' parameter is provided, or null if not found.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException If validation fails.
     */
    private function getAction(Request $request, EloquentCollection $actions): ?Action
    {
        // Validate the 'action' parameter from the request against the specified rules
        $validator = Validator::make($request->only('action'), [
            'action' => ['numeric', 'integer', Rule::in($actions->pluck('id'))], // Ensure it's a valid action ID
        ]);

        // If validation fails, trigger a 404 error response to indicate the resource was not found
        if ($validator->fails()) {
            abort(404);
        }

        // Attempt to retrieve the action from the database using the validated 'action' ID
        // return Action::select(['id', 'name'])->find($request->get('action'));    // commented on 18.10.2024 12:08
        return Action::select(['id', 'name'])->find($request->query('action'));
    }

    /**
     * Retrieve a paginated collection of adverts based on the provided category, region, and action.
     *
     * This method fetches adverts that match the specified category and, optionally, the region and action.
     * It only returns active and non-expired adverts.
     * If no category is provided, it returns an empty collection.
     *
     * @param  Category|null  $category  The category to filter adverts by. If null, no adverts will be returned.
     * @param  Region|null  $region  The region to filter adverts by, or null if no region is specified.
     * @param  Action|null  $action  The action to filter adverts by, or null if no action is specified.
     * @return LengthAwarePaginator A paginated collection of adverts.
     *                              Each advert includes 'id', 'title', 'action_id', 'region_id', and the region name.
     */
    private function getAdverts(?Category $category, ?Region $region, ?Action $action): LengthAwarePaginator
    {
        // If no category is provided, return empty collections for adverts
        if (! $category) {
            return new EloquentCollection();
        }

        // Build a query to fetch adverts based on the specified category and region
        $query = Advert::select('id', 'title', 'category_id', 'action_id', 'region_id')
            ->notExpired()             // Include only adverts that are not expired
            ->active()                 // Include only active adverts
            ->forCategory($category)   // Filter adverts by the specified category
            ->forRegion($region)       // Filter adverts by the specified region (if provided)
            ->forAction($action)       // Filter adverts by the specified action (if provided)
            ->with([
                'region:id,name',
                'defaultPhoto',
                'attributesWithValues',
            ]);

        return $query->paginate(20);
    }

    /**
     * Retrieve the adverts table column attributes for the specified category and action.
     *
     * @param  Category  $category  The category for which to fetch column attributes. This parameter is required.
     * @param  Action|null  $action  The selected action that may influence the column attributes, or null if no action is specified.
     * @return EloquentCollection A collection of attributes for rendering as the columns names of an adverts table.
     */
    private function getColumnAttributes(Category $category, ?Action $action): EloquentCollection
    {
        // Retrieve available attributes for category and action
        $allAvailableAttributes = $this->categoryAttributeService->getAllAvailableAttributes($category, $action);

        // Retrieve the attributes for the columns based on the available attributes and selected action
        return $this->categoryAttributeService->getColumnAttributes($allAvailableAttributes, $action);
    }

    /**
     * Split a collection of items into multiple columns for rendering in the view.
     *
     * This method takes a collection of items and splits it into chunks, where each chunk represents
     * a column. The maximum number of columns allowed is 4. If the total count of items is less than
     * 49, the number of columns is determined by dividing the item count by 12 (with a ceiling function).
     * Otherwise, the items are split into a fixed 4 columns.
     *
     * @param  EloquentCollection  $items  The collection of items to be split.
     * @return EloquentCollection A collection of item chunks, each representing a column.
     */
    private function splitCollectionByColumns(EloquentCollection $items): EloquentCollection
    {
        // Count the total number of items in the collection
        $itemsCount = count($items);

        // Calculate the number of columns needed, with a minimum of 12 items per column
        $columnsCount = ceil($itemsCount / 12);

        // If the total count of items is less than 49, split by calculated columns
        // Otherwise, split into a maximum of 4 columns
        if ($itemsCount < 49) {
            $itemsChunks = $items->split($columnsCount);
        } else {
            $itemsChunks = $items->split(4);
        }

        // Return the resulting collection of item chunks
        return $itemsChunks;
    }

    /**
     * Get the advert counts for a given collection of regions
     * (aggregated advert counts of each region's leaf descendants).
     *
     * @param  Category  $category  The category for which advert counts are to be retrieved.
     * @param  SupportCollection  $regions  A collection of Region models for which advert counts are to be retrieved.
     * @return SupportCollection|\stdClass[] A collection of objects, each containing:
     *                                       - id: The ID of the region (int)
     *                                       - name: The name of the region (string)
     *                                       - advert_count: The total count of adverts associated with this region (int).
     *
     * This method executes a single SQL query to aggregate the number of adverts for each specified region,
     * including its leaf descendants, efficiently using left joins.
     */
    private function getRegionsAdvertsCounts(Category $category, SupportCollection $regions): SupportCollection
    {
        // Step 1: Extract the IDs of the provided regions
        $regionsIds = $regions->pluck('id');

        // Step 2: Fetch the advert counts for the provided regions
        /**
         * @var SupportCollection|\stdClass[] $regionsWithAdvertCounts
         *
         * A collection of objects where each object represents a region and includes:
         * - id: The ID of the region (int)
         * - name: The name of the region (string)
         * - advert_count: The total count of adverts associated with this region (int).
         *
         * This collection is populated by executing a query that counts the number of adverts
         * for each region and its descendants, filtered by the provided regions.
         */
        $regionsWithAdvertCounts = DB::table('regions as parent')
            ->leftJoin('regions as child', function ($join): void {
                // Perform a self-join to include child regions in the counting process
                // The join condition ensures that child regions fall within the range of the parent region
                $join->on('child._lft', '>=', 'parent._lft') // The left boundary of the child must be greater than or equal to the parent's left boundary
                    ->on('child._rgt', '<=', 'parent._rgt'); // The right boundary of the child must be less than or equal to the parent's right boundary
            })
            ->leftJoin('advert_adverts', 'advert_adverts.region_id', '=', 'child.id')
            // Join the adverts table to count the number of adverts linked to each child region
            ->select(
                'parent.id', // Select the parent region ID
                'parent.name', // Select the parent region name
                DB::raw('COUNT(advert_adverts.id) as adverts_count') // Count the number of adverts for the parent region and its descendants
            )
            ->whereIn('parent.id', $regionsIds) // Filter by the IDs of the provided regions
            ->where('advert_adverts.category_id', '=', $category->id)
            ->where('advert_adverts.status', '=', 'active') // Add condition for active adverts
            ->where('advert_adverts.expires_at', '>', now()) // Add condition for non-expired adverts
            ->groupBy('parent.id', 'parent.name') // Group the results by parent region ID and name
            ->get(); // Execute the query and retrieve the results as a collection

        return $regionsWithAdvertCounts;
    }
}
