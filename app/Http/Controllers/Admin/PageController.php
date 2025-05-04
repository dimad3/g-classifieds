<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Page;
use App\Services\PageService;
use Illuminate\Http\Request;

// todo: path cache clear after page adding or editing or deleting
class PageController extends Controller
{
    private PageService $pageService;

    /**
     * Constructor to inject the PageService and apply middleware for page management.
     *
     * @param  PageService  $pageService  The service for handling page logic.
     */
    public function __construct(PageService $pageService)
    {
        $this->pageService = $pageService;
        $this->middleware('can:manage-pages');
    }

    public function index()
    {
        // Fetch root pages, along with their children, and paginate them
        $pages = Page::whereIsRoot()
            ->orderBy('sort')
            ->orderBy('menu_title')
            ->orderBy('slug')
            ->with('children')
            ->paginate(20);

        // Return the view with pages data
        return view('admin.pages.index', compact('pages'));
    }

    /**
     * Show the form for **creating** a new page (root or sub-page).
     *
     * @param  Request  $request  The HTTP request object, which may contain a `parent_id` query parameter.
     * @return \Illuminate\View\View The view containing the form for creating or editing a page.
     */
    public function create(Request $request)
    {
        // If a parent page is specified via 'parent_id' query parameter, retrieve it for sub-page creation;
        $parentPage = $request->query('parent_id') ? Page::findOrFail($request->input('parent_id')) : null;

        // Return the create/edit view.
        // The form can either create a **root page** or a **sub-page**, depending on whether a $parentPage is provided.
        return view('admin.pages.create_or_edit', [
            // A new Page instance is passed here for the creation process.
            // For updates, an existing Page instance is passed via the edit() method.
            'page' => new Page(),
            // If `$parentPage` is `null`, a **root page** will be created.
            // If `$parentPage` is provided, a **sub-page** will be created under the specified parent.
            'parentPage' => $parentPage,
        ]);
    }

    /**
     * Store a newly created page in the database.
     *
     * @param  PageRequest  $request  The validated request data.
     * @return \Illuminate\Http\RedirectResponse Redirect to the newly created page after storing.
     */
    public function store(PageRequest $request)
    {
        // Use the PageService to handle storing the new page
        $page = $this->pageService->storePage($request);

        // Redirect to the show page with a success message
        return redirect()->route('admin.pages.show', $page)->with('success', 'Page created successfully!');
    }

    /**
     * Display the specified page along with its ancestors and sub-pages.
     *
     * @param  Page  $page  The page to display.
     * @return \Illuminate\View\View The view showing the page details.
     */
    public function show(Page $page)
    {
        // Retrieve the ancestors of the specified page and sort them from the root ancestor to the current node
        $ancestors = $page->ancestors()->get()->sortBy('_lft');
        // Fetch sub-pages that belong to the current page, ordered by sort and menu_title.
        $subPages = Page::where('parent_id', $page->id)
            ->orderBy('sort')
            ->orderBy('menu_title')
            ->paginate(20);

        // Return the view with the page, its ancestors, and sub-pages data.
        return view('admin.pages.show', compact('page', 'ancestors', 'subPages'));
    }

    /**
     * Show the form for **editing** an existing page.
     *
     * @param  Page  $page  The page being edited.
     * @return \Illuminate\View\View The view containing the form for editing the page.
     */
    public function edit(Page $page)
    {
        // dd($this->pageService->getAvailablePagesForParentDropdown($page));
        return view('admin.pages.create_or_edit', [
            // The existing `Page` instance is passed here for the editing process.
            // This pre-populates the form with the current data of the page.
            // For **creating**, a new, empty `Page` instance is passed via the create() method.
            'page' => $page,
            // The current parent of the page, if any, is passed to the view.
            'parentPage' => $page->parent,
            // Provide a list of available pages excluding the page itself and its descendants
            // to prevent circular relationships.
            // Is used for potential reassignment as a parent page in the dropdown.
            'parents' => $this->pageService->getAvailablePagesForParentDropdown($page),
        ]);
    }

    /**
     * Update the specified page in storage.
     *
     * @param  PageRequest  $request  The validated request data.
     * @param  Page  $page  The page to update.
     * @return \Illuminate\Http\RedirectResponse Redirect to the page's detail page after updating.
     */
    public function update(PageRequest $request, Page $page)
    {
        // Use the PageService to handle the update process
        $this->pageService->updatePage($request, $page);

        // Redirect to the show page with a success message
        return redirect()->route('admin.pages.show', $page)->with('success', 'Page updated successfully!');
    }

    // public function destroy(Page $page)
    // {
    //     $page->delete();

    //     return redirect()->route('admin.pages.index');
    // }

    /**
     * Remove the specified page from storage.
     *
     * @param  Page  $page  The page to delete.
     * @return \Illuminate\Http\RedirectResponse Redirect after deletion.
     */
    public function destroy(Page $page)
    {
        try {
            // Store the parent page before deleting the current page
            $parentPage = $page->parent;

            // Delegate the deletion logic to the service class
            $this->pageService->deletePage($page);

            // If the parent page exists, redirect to its page, otherwise redirect to the index page
            $redirectRoute = $parentPage
                ? route('admin.pages.show', $parentPage)
                : route('admin.pages.index');

            return redirect($redirectRoute)->with('success', 'Page deleted successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
