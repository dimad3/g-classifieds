<?php

declare(strict_types=1);

namespace App\Services\Adverts;

use App\Http\Requests\Admin\Categories\CategoryRequest;
use App\Jobs\FixCategoryTree;
use App\Jobs\RebuildCategoriesPaths;
use App\Models\Adverts\Category;
use Illuminate\Support\Facades\Cache;

class CategoryService
{
    /**
     * $category - for editing it is category, for adding sub category -> it is parent category
     */
    public function storeOrUpdate(CategoryRequest $request, Category $category, ?int $parentCategoryId): bool
    {
        $category->name = $request->name;
        $category->sort = $request->sort;
        $this->setOtherData($request, $category, $parentCategoryId);

        $result = $category->save();
        FixCategoryTree::dispatch();
        RebuildCategoriesPaths::dispatch();

        return $result;
    }

    /**
     * Remove the category from the database.
     */
    public function destroy(Category $category): void
    {
        $category->delete();
        FixCategoryTree::dispatch();
        RebuildCategoriesPaths::dispatch();
    }

    public function rebuildCategoriesPaths(): void
    {
        // 3-nd approach = 3500 ms
        $categories = Category::orderBy('sort')->orderBy('name')->withDepth()->get()->toFlatTree();
        foreach ($categories as $category) {
            $categoriesPaths[] = ['id' => $category->id, 'text' => $category->getPath(), 'depth' => $category->depth];

            // put Categories Paths in Cache for setting urls of categories in views\adverts\index.blade.php
            // see also App\Http\Router\AdvertsPath->getRouteKey()
            // rememberForever($key, Closure $callback) -> get an item from the cache, or execute the given Closure and store the result forever.
            Cache::tags(Category::class)
                ->rememberForever('category_path_' . $category->id, function () use ($category) {
                    return $category->getPath();
                });
        }
        Cache::forget('categoriesPaths');
        Cache::forever('categoriesPaths', $categoriesPaths);
    }

    private function setOtherData(CategoryRequest $request, Category $category, ?int $parentCategoryId): void
    {
        if ($category->exists) {
            $this->setDataOnUpdate($request, $category, $parentCategoryId);
        } else {
            $this->setDataOnStore($request, $category, $parentCategoryId);
        }
    }

    /**
     * Set slug & parent_id on adding new subcategory OR parent category
     */
    private function setDataOnStore(CategoryRequest $request, Category $category, ?int $parentCategoryId): void
    {
        $category->slug = $category->makeUniqueSlugForParent($request->name, $parentCategoryId);
        $parentCategoryId ? $category->parent_id = $parentCategoryId : $category->parent_id = null;
    }

    /**
     * Set slug & parent_id on updating category
     */
    private function setDataOnUpdate(CategoryRequest $request, Category $category, ?int $parentCategoryId): void
    {
        // update category
        if (! $parentCategoryId) {
            $category->slug = $request->slug;
            $category->parent_id = $request->parent_id;
        }
    }

    // // 1-st approach = 6000 ms
    // public function one()
    // {
    //     Cache::forget('categoriesPaths');
    //     $parents = Category::whereIsRoot()->orderBy('sort')->orderBy('name')->withDepth()->getModels();
    //     $categoriesPaths = [];

    //     foreach ($parents as $parent) {
    //         $categoriesPaths[] = ['id' => $parent->id, 'path' => $parent->getPath()];
    //         $children = $parent->children()->orderBy('sort')->orderBy('name')->withDepth()->getModels();
    //         // https://blog.hubspot.com/website/html-space
    //         foreach ($children as $child) {
    //             $indent = '';
    //             for ($i = 0; $i < $child->depth; $i++) {
    //                 $indent .= '&emsp;';
    //             }
    //             $categoriesPaths[] = ['id' => $child->id, 'path' => $indent . $child->getPath()];
    //             $children2 = $child->children()->orderBy('sort')->orderBy('name')->withDepth()->getModels();
    //             foreach ($children2 as $child2) {
    //                 $indent = '';
    //                 for ($i = 0; $i < $child2->depth; $i++) {
    //                     $indent .= '&emsp;';
    //                 }
    //                 $categoriesPaths[] = ['id' => $child2->id, 'path' => $indent . $child2->getPath()];
    //                 $children3 = $child2->children()->orderBy('sort')->orderBy('name')->withDepth()->getModels();
    //                 foreach ($children3 as $child3) {
    //                     $indent = '';
    //                     for ($i = 0; $i < $child3->depth; $i++) {
    //                         $indent .= '&emsp;';
    //                     }
    //                     $categoriesPaths[] = ['id' => $child3->id, 'path' => $indent . $child3->getPath()];
    //                 }
    //             }
    //         }
    //     }
    //     Cache::put('categoriesPaths', $categoriesPaths);
    //     dump("Parents' Paths were rebuilt");
    // }

    // 2-nd approach = 7000 ms
    // public function two()
    // {
    //     Cache::forget('categoriesPaths');
    //     $roots = Category::whereIsRoot()->orderBy('sort')->orderBy('name')->withDepth()->getModels();
    //     foreach ($roots as $category) {
    //         $this->setPaths($category);
    //     }
    //     Cache::put('categoriesPaths', $this->paths);
    //     dump("Parents' Paths were rebuilt");
    // }

    // public $paths = [];
    // // https://stackoverflow.com/questions/69127801/laravel-how-to-get-all-nested-subcategories-recursively
    // private function setPaths(Category $category)
    // {
    //     $this->paths[] = ['id' => $category->id, 'path' => $category->getPath()];
    //     // $this->paths[] = $category->name;
    //     foreach ($category->children()->orderBy('sort')->orderBy('name')->withDepth()->getModels() as $child) {
    //         $this->setPaths($child);
    //     }
    // }
}
