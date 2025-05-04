<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet\Adverts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adverts\StoreRequest;
use App\Models\Action\Action;
use App\Models\Adverts\Advert\Advert;
use App\Models\Adverts\Category;
use App\Models\Region;
use App\Services\Adverts\AdvertService;
use App\Services\Adverts\CategoryAttributeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Class CreateController
 *
 * This controller handles the creation process for adverts within the user's cabinet.
 * It provides step-by-step methods for selecting categories, regions, and actions,
 * allowing users to customize their advert settings in a sequential manner.
 *
 * The main responsibilities of this controller include:
 * - Displaying root and subcategories for advert categorization.
 * - Showing root or subregions depending on the user's previous selections.
 * - Allowing users to select actions specific to the chosen category it it has any.
 * - Displaying and validating a form to input advert details based on selected criteria.
 * - Storing the completed advert in the database.
 *
 * Each method follows a logical progression, starting from category selection,
 * followed by region and action selection, and ending with the advert creation form
 * and storage process.
 *
 * Dependencies:
 * - AdvertService: Provides core functionality for storing and managing adverts.
 */
class CreateController extends Controller
{
    protected AdvertService $advertService;

    protected CategoryAttributeService $categoryAttributeService;

    public function __construct(AdvertService $advertService, CategoryAttributeService $categoryAttributeService)
    {
        $this->advertService = $advertService;
        $this->categoryAttributeService = $categoryAttributeService;
    }

    /**
     * Display the root categories selection view.
     *
     * @return \Illuminate\View\View
     */
    public function selectRootCategory()
    {
        // Retrieve only root categories, sorted by 'sort' and 'name'
        $categories = Category::whereIsRoot()->orderBy('sort')->orderBy('name')->get();

        return view('cabinet.adverts.create.select_category', compact('categories'));
    }

    /**
     * Display the child categories of a selected parent category.
     *
     * @param  Category  $category  The parent category to retrieve subcategories for
     * @return \Illuminate\View\View
     */
    public function selectSubCategory(Category $category)
    {
        // Fetch child categories, with their own children, sorted by 'sort' and 'name'
        $subCategories = $category
            ->children()
            ->with('children')
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        return view('cabinet.adverts.create._sub_categories_list', compact('subCategories'));
    }

    /**
     * Display a view to select a region, showing either root regions or subregions depending on selection.
     *
     * If the category does not have subcategories, the method initially displays all root regions for selection.
     * When a region is selected, if that region has subregions, it displays those subregions instead.
     * The regions are split into columns to optimize the display layout.
     *
     * @param  Category  $category  The selected advert category
     * @param  Region|null  $region  The selected parent region, if any
     * @return \Illuminate\View\View
     */
    public function selectRegion(Category $category, ?Region $region = null)
    {
        // Store the selected region as a parent region reference
        $parentRegion = $region;

        // Determine whether to fetch root regions or subregions based on the selected region
        ($regions = Region::where('parent_id', $region ? $region->id : null)
            ->with('children')
            ->orderBy('sort')
            ->orderBy('name')
            ->get());

        // Calculate the number of regions and split them into chunks for display in columns
        $regionsCount = count($regions);

        if ($regionsCount < 49) {
            // For fewer than 49 regions, display in columns of 12 for a balanced view
            $regionsChunks = $regions->chunk(12);
        } else {
            // For 49 or more regions, divide into 4 columns, balancing the distribution
            if ((int) (fmod($regionsCount, 4)) === 0) {
                // Split evenly if the region count divides cleanly by 4
                $regionsChunks = $regionsChunks = $regions->chunk((int) ($regionsCount / 4));
            } else {
                // Otherwise, add one extra region to each column to distribute remainder
                $regionsChunks = $regionsChunks = $regions->chunk((int) ($regionsCount / 4) + 1);
            }
        }

        $actions = $category->getAdjustedActions($category->ancestorsAndMe());

        // Render the view with the selected category, parent region if any, and prepared region chunks
        return view('cabinet.adverts.create.select_region', compact('category', 'parentRegion', 'regionsChunks', 'actions'));
    }

    /**
     * Display available actions for a selected category.
     *
     * @param  Category  $category  The selected category for the advert
     * @param  Region  $region  The selected region for the advert
     * @return \Illuminate\View\View
     */
    public function selectAction(Category $category, Region $region)
    {
        // Retrieve actions available for the category and its ancestors
        $actions = $category->getAdjustedActions($category->ancestorsAndMe());

        return view('cabinet.adverts.create.select_action', compact('category', 'region', 'actions'));
    }

    /**
     * Display form for creating a new advert.
     *
     * @param  Category  $category  The selected category for the advert
     * @param  Region  $region  The selected region for the advert
     * @param  Request  $request  HTTP request containing action data
     * @return \Illuminate\View\View
     */
    public function create(Category $category, Region $region, Request $request)
    {
        // Validate selected action based on available actions for the category
        $adjustedActions = $category->getAdjustedActions($category->ancestorsAndMe());
        $request->validate([
            'action' => [
                'nullable',
                Rule::requiredIf(fn () => $adjustedActions->isNotEmpty()),
                'integer',
                Rule::in($adjustedActions->pluck('id')),
            ],
        ]);

        $action = $request['action'] ? Action::findOrFail($request['action']) : null;
        $advert = new Advert();

        // Retrieve available attributes for category and action
        $availableAttributes = $this->categoryAttributeService->getAllAvailableAttributes($category, $action);
        // Retrieve required attributes for category and action
        $requiredAttributes = $this->categoryAttributeService->getRequiredAttributes($availableAttributes, $action);

        $advertAttributes = compact('availableAttributes', 'requiredAttributes');

        return view('cabinet.adverts.create_or_edit', compact('category', 'region', 'action', 'advert', 'advertAttributes'));
    }

    /**
     * Store a new advert in the database.
     *
     * @param  StoreRequest  $request  Validated request containing advert data
     * @param  Category  $category  The selected category for the advert
     * @param  Region  $region  The selected region for the advert
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreRequest $request, Category $category, Region $region)
    {
        try {
            // Store the new advert and link it to the authenticated user, category, and region
            $advert = $this->advertService->storeAdvert(
                Auth::id(),
                $category->id,
                $region->id,
                $request
            );
        } catch (\DomainException $e) {
            // Handle domain exceptions, redirecting back with an error message
            return back()->with('error', $e->getMessage());
        }

        // Redirect to the advert's detail page after successful creation
        return redirect()->route('adverts.show', $advert);
    }
}
