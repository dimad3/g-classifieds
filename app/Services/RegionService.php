<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\Admin\RegionRequest;
use App\Jobs\FixRegionTree;
use App\Jobs\RebuildRegionsPaths;
use App\Models\Region;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| RegionService
|--------------------------------------------------------------------------
|
| Service class for handling business logic related to Regions.
| This service abstracts the logic for storing, updating, deleting, and managing regions.
|
*/

class RegionService
{
    /**
     * Store a new region in the database.
     *
     * @param  RegionRequest  $request  The validated request data.
     * @return Region The created region instance.
     */
    public function storeRegion(RegionRequest $request): Region
    {
        // Create a new Region instance
        $region = new Region();
        // Get parent_id from the query parameters and cast to int, or use null if it's not presented
        $parentId = $request->query('parent_id') ? (int) $request->query('parent_id') : null;

        // Save the new region using the helper method.
        // A new Region instance is passed here for the creation process.
        // For updates, an existing Region instance is passed via the updateRegion() method.
        // If `$parentId` is `null`, a **root region** will be created.
        // If `$parentId` is provided, a **sub-region** will be created under the specified parent.
        return $this->saveRegion($request, $region, $parentId);
    }

    /**
     * Update an existing region in the database.
     *
     * @param  RegionRequest  $request  The validated request data.
     * @param  Region  $region  The region to update.
     * @return Region The updated region instance.
     */
    public function updateRegion(RegionRequest $request, Region $region): Region
    {
        // Get parent_id from the request input (or set it to null if not provided)
        $parentId = $request->input('parent_id', null) ? (int) $request->input('parent_id') : null;

        // Update the region using the helper method.
        // Here, the existing `Region` instance is passed for the update process.
        // For creation of new regions, a new Region instance is passed via the storeRegion() method.
        return $this->saveRegion($request, $region, $parentId);
    }

    /**
     * Delete a region and ensure no sub-regions or adverts exist before deletion.
     *
     * @param  Region  $region  The region to delete.
     *
     * @throws \Exception If the region has sub-regions or adverts.
     */
    public function deleteRegion(Region $region): void
    {
        // Prevent deletion if the region has sub-regions or adverts
        if ($region->children()->exists()) {
            throw new \Exception('You cannot delete a region that has sub-regions. Delete the sub-regions first.');
        }

        if ($region->adverts()->exists()) {
            throw new \Exception('You cannot delete a region that has adverts! Delete the adverts first.');
        }

        // Delete the region and rebuild region paths
        $region->delete();

        // Dispatch any necessary jobs
        FixRegionTree::dispatch();
        RebuildRegionsPaths::dispatch();
    }

    /**
     * Generate a unique slug for a region within its parent region.
     *
     * This method transforms the given name into a URL-friendly slug and ensures
     * the slug is unique within the same parent region. If similar slugs already exist
     * in the database, a numeric suffix is appended to the slug.
     *
     * Example: If 'new-york' exists, the next will be 'new-york-1', 'new-york-2', etc.
     *
     * @param  string  $name  The name to be transformed into a slug.
     * @param  int|null  $parentId  The ID of the parent region where the slug must be unique.
     *                              If null, the region is considered a root region.
     * @return string A unique slug for the region.
     */
    public function generateUniqueSlug(string $name, ?int $parentId): string
    {
        // Convert the region name into a slug using Laravel's Str::slug() method.
        $slug = \Str::slug($name);

        // Check if any regions with the same parent_id already have this slug or a variant with a numeric suffix.
        $count = Region::where('parent_id', $parentId)
            // You can uncomment the next line to exclude the current region's ID from the check,
            // useful when updating an existing region's slug.
            // in this service class unique slug is generated only on store() not on uptate(), so it remain commented
            // ->whereKeyNot($region->id)

            // Use a raw SQL query to match slugs that have the same base name or a numeric suffix.
            ->whereRaw("slug RLIKE '^{$slug}(-[0-9]+)?$'")
            ->count();

        // If matching slugs are found, append a numeric suffix to the base slug.
        return $count ? "{$slug}-{$count}" : $slug;
    }

    /**
     * Get available regions for the parent region dropdown in the edit view (excluding the current region and its descendants).
     *
     * This method is used to retrieve a list of regions that can be selected as a parent when editing a region.
     * It excludes the current region and all of its descendants to avoid cyclic parent-child relationships.
     *
     * @param  Region  $region  The region being edited.
     * @return array An array of available regions (excluding the current region and its descendants).
     */
    public function getAvailableRegionsForParentDropdown(Region $region): array
    {
        // If the provided region exists, proceed to fetch the available regions.
        if ($region) {
            // Retrieve all cached regions and their paths (as a collection).
            // 'regionsPaths' is assumed to be a cache of all regions with their hierarchical paths.
            $regionsPaths = collect(Cache::get('regionsPaths'));

            // Get the IDs of the current region and all of its descendants to exclude them from the dropdown.
            $descendantIds = Region::descendantsAndSelf($region->id)->pluck('id')->toArray();

            // Filter out the current region and its descendants from the available regions.
            return $regionsPaths->whereNotIn('id', $descendantIds)->toArray();
        }

        // If no region is provided, return an empty array as there are no available regions.
        return [];
    }

    /**
     * Rebuilds the cache for regions' paths.
     *
     * This method is responsible for rebuilding and caching the paths of all regions.
     * Each region's path is stored with its unique identifier, textual representation,
     * and depth in the tree.
     */
    public function rebuildRegionsPaths(): void
    {
        // Retrieve all regions, ordered by sort and name, with depth information.
        ($regions = Region::orderBy('sort')->orderBy('name')->withDepth()->get()->toFlatTree());
        // ($regions = Region::whereIn('id', [678, 683, 695, 712])->orderBy('sort')->orderBy('name')->withDepth()->get()->toFlatTree());

        // Initialize an array to store regions' paths for cache storage.
        $regionsPaths = [];

        // Loop through each region and build its path information.
        foreach ($regions as $region) {
            // Store the region ID, path string, and region depth in the regionsPaths array.
            $regionPath = $region->getPath();
            $regionsPaths[] = ['id' => $region->id, 'text' => $regionPath, 'depth' => $region->depth];

            // Cache each region's path individually using a unique key.
            // This allows paths to be retrieved in views, e.g. as adverts/index.blade.php.
            // todo: 12.10.2024 why do we need to use tags()
            Cache::tags(Region::class)
                // rememberForever($key, Closure $callback) -> get an item from the cache, or execute the given Closure and store the result forever.
                ->rememberForever('region_path_' . $region->id, function () use ($regionPath) {
                    return $regionPath;
                });
        }
        // dd($regionsPaths);
        // Clear any existing cached regions' paths.
        Cache::forget('regionsPaths');

        // Store the new regions' paths in the cache for future use.
        Cache::forever('regionsPaths', $regionsPaths);
    }

    /**
     * Helper method to save or update a region in the database.
     *
     * @param  RegionRequest  $request  The validated request data.
     * @param  Region  $region  The region instance to save or update.
     * @param  int|null  $parentId  The ID of the parent region (if any).
     * @return Region The saved or updated region.
     */
    private function saveRegion(RegionRequest $request, Region $region, ?int $parentId): Region
    {
        // Set the region name
        $region->name = $request->input('name');

        // Generate a unique slug only if the region is being created (not updated)
        if (! $region->exists) {
            $region->slug = $this->generateUniqueSlug($region->name, $parentId); // Generate slug for new region
        } else {
            // On update, use the slug from the request input
            $region->slug = $request->input('slug');
        }

        // Set other attributes
        $region->sort = $request->input('sort', 200);
        $region->parent_id = $parentId;

        // Save the region to the database
        $region->save();

        // Dispatch any necessary jobs
        FixRegionTree::dispatch();
        RebuildRegionsPaths::dispatch();

        return $region;
    }
}
