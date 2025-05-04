<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegionRequest;
use App\Models\Region;
use App\Services\RegionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/*
    |--------------------------------------------------------------------------
    | RegionController
    |--------------------------------------------------------------------------
    |
    | Controller for managing regions in the admin panel.
    | This handles CRUD operations, including creating, updating, deleting, and displaying regions.
    |
    */

class RegionController extends Controller
{
    private RegionService $regionService;

    /**
     * Constructor to inject the RegionService and apply middleware for region management.
     *
     * @param  RegionService  $regionService  The service for handling region logic.
     */
    public function __construct(RegionService $regionService)
    {
        $this->regionService = $regionService;
        $this->middleware('can:manage-regions');
    }

    /**
     * Display a paginated list of root regions with their children.
     *
     * @return \Illuminate\View\View The view displaying the regions.
     */
    public function index()
    {
        // Fetch root regions, along with their children, and paginate them
        $regions = Region::whereIsRoot()
            ->orderBy('sort')
            ->orderBy('name')
            ->orderBy('slug')
            ->with('children')
            ->paginate(20);

        // Return the view with regions data
        return view('admin.regions.index', compact('regions'));
    }

    /**
     * Show the form for **creating** a new region (root or sub-region).
     *
     * @param  Request  $request  The HTTP request object, which may contain a `parent_id` query parameter.
     * @return \Illuminate\View\View The view containing the form for creating or editing a region.
     */
    public function create(Request $request)
    {
        // If a parent region is specified via 'parent_id' query parameter, retrieve it for sub-region creation;
        $parentRegion = $request->query('parent_id') ? Region::findOrFail($request->input('parent_id')) : null;

        // Return the create/edit view.
        // The form can either create a **root region** or a **sub-region**, depending on whether a $parentRegion is provided.
        return view('admin.regions.create_or_edit', [
            // A new Region instance is passed here for the creation process.
            // For updates, an existing Region instance is passed via the edit() method.
            'region' => new Region(),
            // If `$parentRegion` is `null`, a **root region** will be created.
            // If `$parentRegion` is provided, a **sub-region** will be created under the specified parent.
            'parentRegion' => $parentRegion,
        ]);
    }

    /**
     * Store a newly created region in the database.
     *
     * @param  RegionRequest  $request  The validated request data.
     * @return \Illuminate\Http\RedirectResponse Redirect to the region's detail page after storing.
     */
    public function store(RegionRequest $request)
    {
        // Use the RegionService to handle storing the new region
        $region = $this->regionService->storeRegion($request);

        // Redirect to the show page with a success message
        return redirect()->route('admin.regions.show', $region)->with('success', 'Region created successfully!');
    }

    /**
     * Display the specified region along with its ancestors and sub-regions.
     *
     * @param  Region  $region  The region to display.
     * @return \Illuminate\View\View The view showing the region details.
     */
    public function show(Region $region)
    {
        // Retrieve the ancestors of the specified region and sort them from the root ancestor to the current node
        $ancestors = $region->ancestors()->get()->sortBy('_lft');
        // Fetch sub-regions that belong to the current region, ordered by sort and name.
        $subRegions = Region::where('parent_id', $region->id)
            ->orderBy('sort')
            ->orderBy('name')
            ->paginate(20);

        // Return the view with the region, its ancestors, and sub-regions data.
        return view('admin.regions.show', compact('region', 'ancestors', 'subRegions'));
    }

    /**
     * Show the form for **editing** an existing region.
     *
     * @param  Region  $region  The region being edited.
     * @return \Illuminate\View\View The view containing the form for editing the region.
     */
    public function edit(Region $region)
    {
        if ($region->exists) {
            if (Cache::missing('regionsPaths')) {
                // run only when 'regionsPaths' are not set in Cache, that is highly unlikely
                $this->regionService->RebuildRegionsPaths();
            }
            $regionsPaths = (array) Cache::get('regionsPaths');

            // exclude self & descendants from array
            $descendants = Region::descendantsAndSelf($region->id);
            foreach ($descendants as $descendant) {
                $regionsPaths = array_filter($regionsPaths, function ($item) use ($descendant) {
                    return $item['id'] !== $descendant->id;
                });
            }
        }
        isset($regionsPaths) ?: $regionsPaths = [];

        return view('admin.regions.create_or_edit', [
            // The existing `Region` instance is passed here for the editing process.
            // This pre-populates the form with the current data of the region.
            // For **creating**, a new, empty `Region` instance is passed via the create() method.
            'region' => $region,
            // The current parent of the region, if any, is passed to the view.
            'parentRegion' => $region->parent,
            // Provide a list of available regions excluding the region itself and its descendants
            // to prevent circular relationships.
            // Is used for potential reassignment as a parent region in the dropdown.
            'regionsPaths' => $this->regionService->getAvailableRegionsForParentDropdown($region),
        ]);
    }

    /**
     * Update the specified region in storage.
     *
     * @param  RegionRequest  $request  The validated request data.
     * @param  Region  $region  The region to update.
     * @return \Illuminate\Http\RedirectResponse Redirect to the region's detail page after updating.
     */
    public function update(RegionRequest $request, Region $region)
    {
        // Use the RegionService to handle the update process
        $this->regionService->updateRegion($request, $region);

        // Redirect to the show page with a success message
        return redirect()->route('admin.regions.show', $region)->with('success', 'Region updated successfully!');
    }

    /**
     * Remove the specified region from storage.
     *
     * @param  Region  $region  The region to delete.
     * @return \Illuminate\Http\RedirectResponse Redirect after deletion.
     */
    public function destroy(Region $region)
    {
        try {
            // Store the parent region before deleting the current region
            $parentRegion = $region->parent;

            // Delegate the deletion logic to the service class
            $this->regionService->deleteRegion($region);

            // If the parent region exists, redirect to its page, otherwise redirect to the index page
            $redirectRoute = $parentRegion
                ? route('admin.regions.show', $parentRegion)
                : route('admin.regions.index');

            return redirect($redirectRoute)->with('success', 'Region deleted successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
