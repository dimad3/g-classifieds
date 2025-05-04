<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Adverts\Category;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function index()
    {
        /**
         * whereIsRoot() - Scope limits query to select just root node.
         *
         * @return $this vendor\kalnoy\nestedset\src\QueryBuilder.php
         */
        $rootCategories = Category::whereIsRoot()
            ->with(['children' => function ($query): void {
                $query->orderBy('sort')->orderBy('name');
            }])
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        return view('home', compact('rootCategories'));
    }
}
