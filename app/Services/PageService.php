<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\Admin\PageRequest;
// use App\Jobs\FixPagesTree;
// use App\Jobs\RebuildPagesPaths;
use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| PageService
|--------------------------------------------------------------------------
|
| Service class for handling business logic related to Pages.
| This service abstracts the logic for storing, updating, deleting, and managing pages.
|
*/

class PageService
{
    /**
     * Store a new page in the database.
     *
     * @param  PageRequest  $request  The validated request data.
     * @return Page The created page instance.
     */
    public function storePage(PageRequest $request): Page
    {
        // Create a new Page instance
        $page = new Page();
        // Get parent_id from the query parameters and cast to int, or use null if it's not presented
        $parentId = $request->query('parent_id') ? (int) $request->query('parent_id') : null;

        // Save the new page using the helper method.
        // A new Page instance is passed here for the creation process.
        // For updates, an existing Page instance is passed via the updatePage() method.
        // If `$parentId` is `null`, a **root page** will be created.
        // If `$parentId` is provided, a **sub-page** will be created under the specified parent.
        return $this->savePage($request, $page, $parentId);
    }

    /**
     * Update an existing page in the database.
     *
     * @param  PageRequest  $request  The validated request data.
     * @param  Page  $page  The page to update.
     * @return Page The updated page instance.
     */
    public function updatePage(PageRequest $request, Page $page): Page
    {
        // Get parent_id from the request input (or set it to null if not provided)
        $parentId = $request->input('parent_id', null) ? (int) $request->input('parent_id') : null;

        // Update the page using the helper method.
        // Here, the existing `Page` instance is passed for the update process.
        // For creation of new pages, a new Page instance is passed via the storePage() method.
        return $this->savePage($request, $page, $parentId);
    }

    /**
     * Delete a page and ensure no sub-pages or adverts exist before deletion.
     *
     * @param  Page  $page  The page to delete.
     *
     * @throws \Exception If the page has sub-pages.
     */
    public function deletePage(Page $page): void
    {
        // Prevent deletion if the page has sub-pages or adverts
        if ($page->children()->exists()) {
            throw new \Exception('You cannot delete a page that has sub-pages. Delete the sub-pages first.');
        }

        // Delete the page and rebuild page paths
        $page->delete();

        // Dispatch any necessary jobs
        // FixPagesTree::dispatch();
        // RebuildPagesPaths::dispatch();
    }

    /**
     * Generate a unique slug for a page within its parent page.
     *
     * This method transforms the given name into a URL-friendly slug and ensures
     * the slug is unique within the same parent page. If similar slugs already exist
     * in the database, a numeric suffix is appended to the slug.
     *
     * Example: If 'new-york' exists, the next will be 'new-york-1', 'new-york-2', etc.
     *
     * @param  string  $name  The name to be transformed into a slug.
     * @param  int|null  $parentId  The ID of the parent page where the slug must be unique.
     *                              If null, the page is considered a root page.
     * @return string A unique slug for the page.
     */
    public function generateUniqueSlug(string $name, ?int $parentId): string
    {
        // Convert the page name into a slug using Laravel's Str::slug() method.
        $slug = \Str::slug($name);

        // Check if any pages with the same parent_id already have this slug or a variant with a numeric suffix.
        $count = Page::where('parent_id', $parentId)
            // You can uncomment the next line to exclude the current page's ID from the check,
            // useful when updating an existing page's slug.
            // in this service class unique slug is generated only on store() not on uptate(), so it remain commented
            // ->whereKeyNot($page->id)

            // Use a raw SQL query to match slugs that have the same base name or a numeric suffix.
            ->whereRaw("slug RLIKE '^{$slug}(-[0-9]+)?$'")
            ->count();

        // If matching slugs are found, append a numeric suffix to the base slug.
        return $count ? "{$slug}-{$count}" : $slug;
    }

    /**
     * Get available pages for the parent page dropdown in the edit view (excluding the current page and its descendants).
     *
     * This method is used to retrieve a list of pages that can be selected as a parent when editing a page.
     * It excludes the current page and all of its descendants to avoid cyclic parent-child relationships.
     *
     * @param  Page  $page  The page being edited.
     * @return Collection A collection of available pages (excluding the current page and its descendants).
     */
    public function getAvailablePagesForParentDropdown(Page $page): Collection
    {
        // If the provided page exists, proceed to fetch the available pages.
        if ($page) {
            // Retrieve all pages
            $pages = Page::select(['id', 'slug', 'parent_id'])->withDepth()->get();

            // Get the IDs of the current page and all of its descendants to exclude them from the dropdown.
            $descendantIds = Page::descendantsAndSelf($page->id)->pluck('id')->toArray();

            // Filter out the current page and its descendants from the available pages.
            return $pages->whereNotIn('id', $descendantIds);
        }

        // If no page is provided, return an empty array as there are no available pages.
        return new Collection;
    }

    /**
     * Helper method to save or update a page in the database.
     *
     * @param  PageRequest  $request  The validated request data.
     * @param  Page  $page  The page instance to save or update.
     * @param  int|null  $parentId  The ID of the parent page (if any).
     * @return Page The saved or updated page.
     */
    private function savePage(PageRequest $request, Page $page, ?int $parentId): Page
    {
        // Set the page titles
        $page->title = $request->input('title');
        $page->menu_title = $request->input('menu_title');

        // Generate a unique slug only if the page is being created (not updated)
        if (! $page->exists) {
            $page->slug = $this->generateUniqueSlug($page->title, $parentId); // Generate slug for new page
        } else {
            // On update, use the slug from the request input
            $page->slug = $request->input('slug');
        }

        // Set other attributes
        $page->sort = $request->input('sort', 200);
        $page->parent_id = $parentId;
        $page->description = $request->input('description');
        $page->content = $request->input('content');

        // Save the page to the database
        $page->save();

        // Dispatch any necessary jobs
        // FixPagesTree::dispatch();
        // RebuildPagesPaths::dispatch();

        return $page;
    }

    // /**
    //  * Rebuilds the cache for pages' paths.
    //  *
    //  * This method is responsible for rebuilding and caching the paths of all pages.
    //  * Each page's path is stored with its unique identifier, textual representation,
    //  * and depth in the tree.
    //  *
    //  * @return void
    //  */
    // public function rebuildPagesPaths(): void
    // {
    //     // Retrieve all pages, ordered by sort and name, with depth information.
    //     ($pages = Page::orderBy('sort')->orderBy('name')->withDepth()->get()->toFlatTree());
    //     // ($pages = Page::whereIn('id', [678, 683, 695, 712])->orderBy('sort')->orderBy('name')->withDepth()->get()->toFlatTree());

    //     // Initialize an array to store pages' paths for cache storage.
    //     $pagesPaths = [];

    //     // Loop through each page and build its path information.
    //     foreach ($pages as $page) {
    //         // Store the page ID, path string, and page depth in the pagesPaths array.
    //         $pagePath = $page->getPath();
    //         $pagesPaths[] = ['id' => $page->id, 'text' => $pagePath, 'depth' => $page->depth];

    //         // Cache each page's path individually using a unique key.
    //         // This allows paths to be retrieved in views, e.g. as adverts/index.blade.php.
    //         // todo: 12.10.2024 why do we need to use tags()
    //         Cache::tags(Page::class)
    //             // rememberForever($key, Closure $callback) -> get an item from the cache, or execute the given Closure and store the result forever.
    //             ->rememberForever('page_path_' . $page->id, function () use ($page, $pagePath) {
    //                 return $pagePath;
    //             });
    //     }
    //     // dd($pagesPaths);
    //     // Clear any existing cached pages' paths.
    //     Cache::forget('pagesPaths');

    //     // Store the new pages' paths in the cache for future use.
    //     Cache::forever('pagesPaths', $pagesPaths);
    // }
}
