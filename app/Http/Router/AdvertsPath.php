<?php

declare(strict_types=1);

namespace App\Http\Router;

use App\Models\Adverts\Category;
use App\Models\Region;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Support\Facades\Cache;

class AdvertsPath implements UrlRoutable
{
    // https://chatgpt.com/c/670b7b0e-4f30-800f-a0b3-9ba7e3e91bbf
    // These properties store the last category and region derived from the URL.
    // They will hold instances of Category and Region, respectively.

    /**
     * @var Category - last category in the url
     */
    public $category;

    /**
     * @var Region - last region in the url
     */
    public $region;

    /**
     * Sets $category property (setter)
     *
     * @param  App\Models\Adverts\Category|null  $category
     * @return App\Http\Router\AdvertsPath
     */
    public function withCategory(?Category $category): self
    {
        // Create a copy of the current instance to maintain immutability,
        // because in PHP 5+ objects are passed by reference
        $clone = clone $this;
        $clone->category = $category;

        // Returns the new instance of AdvertsPath with the updated category.
        return $clone;
    }

    /**
     * Sets $region property (setter)
     *
     * @param  App\Models\Adverts\Region|null  $region
     * @return App\Http\Router\AdvertsPath
     */
    public function withRegion(?Region $region): self
    {
        // Create a copy of the current instance to maintain immutability,
        // because in PHP 5+ objects are passed by reference
        ($clone = clone $this);
        $clone->region = $region;

        // Returns the new instance of AdvertsPath with the updated region.
        return $clone;
    }

    /**
     * Sets $region property to null (setter)
     *
     * @param  App\Models\Adverts\Region|null  $region
     * @return App\Http\Router\AdvertsPath
     */
    public function withoutRegion(): self
    {
        // Create a copy of an object, because in PHP 5+ objects are passed by reference
        $clone = clone $this;
        $clone->region = null;

        return $clone;
    }

    /**
     * Purpose: Constructs the URL path based on the category and region.
     * Caching: Uses rememberForever to cache the paths for performance. It retrieves the path using the getPath() method of the respective models.
     * Return: Concatenates the segments into a single string representing the URL.
     * Example: darbs-un-bizness/vakances/administrators/riga/agenskalns
     */
    public function getRouteKey(): string
    {
        $segments = [];

        if ($this->category) {
            // rememberForever($key, Closure $callback) -> get an item from the cache, or execute the given Closure and store the result forever.
            $segments[] = Cache::tags(Category::class)
                ->rememberForever('category_path_' . $this->category->id, function () {
                    return $this->category->getPath();
                });
        }

        if ($this->region) {
            // https://laravel.com/docs/8.x/cache#retrieve-store
            $segments[] = Cache::tags(Region::class)->rememberForever('region_path_' . $this->region->id, function () {
                return $this->region->getPath();
            });
        }

        return implode('/', $segments);
    }

    /**
     * Purpose: Returns the string 'adverts_path', indicating how the model instance will be referenced in route bindings.
     */
    public function getRouteKeyName(): string
    {
        return 'adverts_path';
    }

    /**
     * Retrieve the models for a bound values ('adverts_path') AND set category AND region properties of AdvertsPath object
     * Purpose: Parses the incoming URL string and resolves the corresponding Category and Region models.
     * Logic:
     * Splits the URL into segments using /.
     * Attempts to find the last valid Category by checking each slug and its parent relationship.
     * Repeats the process for Region.
     * If there are leftover segments after resolving, it triggers a 404 error.
     * Return: Sets the resolved category and region into the instance.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // see chatgpt-explanations\2024.09.21 about parameters of resolveRouteBinding().docx
        // Lesson 8 - 1:12:00 - explanation
        // parse url - get all categories & all regions slugs fom  url as array
        // dump($value);  // $value = string of all categories' AND regions' slugs in the url
        $chunks = explode('/', $value);
        // dump($chunks);   // $chunks = array of slugs

        /** @var Category|null $category */
        $category = null;
        // find last category in the url
        do {
            // Set the internal pointer of an array to its first element and get the value of the first array element, or false if the array is empty
            $slug = reset($chunks);
            if ($slug && $next = Category::where('slug', $slug)->where('parent_id', $category ? $category->id : null)->first()) {
                $category = $next;
                // Shift an element off the beginning of array and get the shifted value, or null if array is empty or is not an array
                array_shift($chunks);
            }
        } while (! empty($slug) && ! empty($next));
        // dd($category->slug);
        // $lastSlug = last($chunks);
        // ($category = Category::where('slug', $lastSlug)->first());

        /** @var Region|null $region */
        // find last region in the url
        $region = null;
        do {
            $slug = reset($chunks);
            if ($slug && $next = Region::where('slug', $slug)->where('parent_id', $region ? $region->id : null)->first()) {
                // dump($region ? $region->name : null, $next ? $next->name : null, "---");
                $region = $next;
                array_shift($chunks);
            }
        } while (! empty($slug) && ! empty($next));
        // dump($region->name);

        if (! empty($chunks)) {
            abort(404);
        }

        return $this
            ->withCategory($category)   // Set $category property
            ->withRegion($region);      // Set $region property
    }

    public function resolveChildRouteBinding($childType, $value, $field): void
    {
    }
}
