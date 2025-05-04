<?php

declare(strict_types=1);

namespace App\Http\ViewComposers;

use App\Models\Page;
use Illuminate\View\View;

/**
 * Class MenuPagesComposer
 *
 * This class is responsible for composing the menu pages data that will be passed to views.
 * It retrieves root pages, orders them by `sort` and `menu_title`, and then chunks them into groups of 6.
 */
class MenuPagesComposer
{
    /**
     * Composes the menu pages data for the view.
     *
     * This method fetches the root pages, orders them first by `sort` and then by `menu_title`,
     * and chunks them into arrays of 6 pages each. The resulting data is then passed to the view
     * as the variable `menuPagesChunks`.
     *
     * @param  View  $view  The view instance that the data will be passed to.
     */
    public function compose(View $view): void
    {
        // Retrieve root pages, order by `sort` and `menu_title`, and chunk into groups of 6
        $view->with(
            'menuPages',
            Page::whereIsRoot() // Get only root pages
                ->orderBy('sort') // Sort by the `sort` field
                ->orderBy('menu_title') // Sort by `menu_title`
                ->get() // Fetch the data
        );
    }
}
