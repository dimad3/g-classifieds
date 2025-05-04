<?php

declare(strict_types=1);

namespace App\Services\Adverts;

use App\Models\Action\Action;
use App\Models\Action\ActionAttributeSetting;
use App\Models\Adverts\Category;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Service for managing and retrieving category attributes with various filtering
 * and processing options, including inherited, excluded, and available attributes.
 */
class CategoryAttributeService
{
    /**
     * Retrieves attributes for the specified category and its ancestors, including the category itself.
     */
    public function getAncestorsAndSelfAttributes(Category $category): EloquentCollection
    {
        return $this->loadAttributes($category->ancestorsAndMe());
    }

    /**
     * Retrieves attributes only for the ancestors of the specified category.
     */
    public function getAncestorsAttributes(Category $category): EloquentCollection
    {
        return $this->loadAttributes($category->ancestors);
    }

    /**
     * Retrieves attributes only for the parent of the specified category.
     */
    public function getParentAttributes(Category $category): EloquentCollection
    {
        return $category->parent ? $category->parent->categoryAttributes : new EloquentCollection();
    }

    /**
     * Retrieves excluded attributes only for the ancestors of the specified category.
     */
    public function getAncestorsAttributesExcluded(Category $category): EloquentCollection
    {
        return $this->loadExcludedAttributes($category->ancestors);
    }

    /**
     * Retrieves excluded attributes for the specified category and its ancestors, including the category itself.
     */
    public function getAncestorsAndSelfAttributesExcluded(Category $category): EloquentCollection
    {
        return $this->loadExcludedAttributes($category->ancestorsAndMe());
    }

    /**
     * Retrieves available attributes only for the ancestors of the specified category,
     * (ancestors' attributes excluding any that are marked as excluded).
     */
    public function getAvailableAncestorsAttributes(Category $category): EloquentCollection
    {
        return $this->filterAttributes(
            $this->getAncestorsAttributes($category),
            $this->getAncestorsAttributesExcluded($category)
        );
    }

    /**
     * Retrieves available attributes for the specified category and its ancestors, excluding any that are marked as excluded.
     */
    public function getAvailableAncestorsAndSelfAttributes(Category $category): EloquentCollection
    {
        return $this->filterAttributes(
            $this->getAncestorsAndSelfAttributes($category),
            $this->getAncestorsAndSelfAttributesExcluded($category)
        );
    }

    /**
     * Retrieves all available attributes for the specified category, optionally considering action-specific exclusions.
     */
    public function getAllAvailableAttributes(Category $category, ?Action $action = null): EloquentCollection
    {
        $availableAttributes = $this->getAvailableAncestorsAndSelfAttributes($category);

        if ($action) {
            $excludedAttributesForAction = $this->filterByAttributeSetting($availableAttributes, 'isExcluded', $action);
            $availableAttributes = $this->filterAttributes($availableAttributes, $excludedAttributesForAction);
        }

        return $availableAttributes;
    }

    /**
     * Retrieves all attributes excluded for the specified category, optionally considering action-specific exclusions.
     */
    public function getAllAttributesExcluded(Category $category, ?Action $action = null): EloquentCollection
    {
        return $this->filterAttributes(
            $this->getAncestorsAndSelfAttributes($category),
            $this->getAllAvailableAttributes($category, $action)
        );
    }

    /**
     * Retrieves required attributes from the provided collection, optionally considering action-specific requirements.
     */
    public function getRequiredAttributes(EloquentCollection $attributes, ?Action $action = null): EloquentCollection
    {
        return $this->filterByAttributeSetting($attributes, 'isRequired', $action);
    }

    /**
     * Retrieves attributes marked as "columns" from the provided collection, optionally considering action-specific requirements.
     */
    public function getColumnAttributes(EloquentCollection $attributes, ?Action $action = null): EloquentCollection
    {
        return $this->filterByAttributeSetting($attributes, 'isColumn', $action);
    }

    /**
     * Retrieves attributes excluded for a specific action from the provided collection.
     */
    public function getExcludedAttributesForAction(EloquentCollection $attributes, ?Action $action = null): EloquentCollection
    {
        return $this->filterByAttributeSetting($attributes, 'isExcluded', $action);
    }

    // Private Helper Methods ======================================================

    /**
     * Loads attributes for the given collection of categories.
     */
    private function loadAttributes(EloquentCollection $categories): EloquentCollection
    {
        $categories->loadMissing('categoryAttributes');

        return new EloquentCollection(
            $categories->flatMap(fn ($category) => $category->categoryAttributes)->sortBy('sort', SORT_NUMERIC)
        );
    }

    /**
     * Loads excluded attributes for the given collection of categories.
     */
    private function loadExcludedAttributes(EloquentCollection $categories): EloquentCollection
    {
        ($categories->loadMissing('inheritedAttributesExcluded'));

        return new EloquentCollection(
            $categories->flatMap(fn ($category) => $category->inheritedAttributesExcluded)->sortBy('sort', SORT_NUMERIC)
        );
    }

    /**
     * Filters a collection of attributes by excluding specified attributes.
     */
    private function filterAttributes(EloquentCollection $attributes, EloquentCollection $excludedAttributes): EloquentCollection
    {
        return new EloquentCollection(
            $attributes->except($excludedAttributes->modelKeys())->sortBy('sort', SORT_NUMERIC)
        );
    }

    /**
     * Filters a collection of attributes based on a specific setting and optional action.
     */
    private function filterByAttributeSetting(EloquentCollection $attributes, string $method, ?Action $action): EloquentCollection
    {
        $ids = ActionAttributeSetting::$method($attributes->modelKeys(), $action)->pluck('attribute_id');

        return new EloquentCollection($attributes->find($ids->toArray()));
    }
}

// 06.12.2024 - to test, try it on the following endpoint: http://ads2.test/test
// class TestController extends Controller
// {
//     protected CategoryAttributeService $categoryAttributeService;

//     public function __construct(CategoryAttributeService $categoryAttributeService)
//     {
//         $this->categoryAttributeService = $categoryAttributeService;
//     }

//     public function test()
//     {
//         $category = Category::find(1362); // checked (363 - ok; 1350 - ok; 751 - ok)
//         // dd($category->ancestors->loadMissing('inheritedAttributesExcluded'));
//         $action = Action::find(8);

//         ($parentAttributes = $this->categoryAttributeService->getParentAttributes($category));
//         foreach ($parentAttributes as $attribute) {
//             dump('parentAttributes: ' . $attribute->id . ' - ' . $attribute->name);
//         }
//         echo '<hr>';

//         ($ancestorsAndSelfAttributes = $this->categoryAttributeService->getAncestorsAndSelfAttributes($category));
//         foreach ($ancestorsAndSelfAttributes as $attribute) {
//             dump('ancestorsAndSelfAttributes: ' . $attribute->id . ' - ' . $attribute->name);
//         }
//         echo '<hr>';

//         ($ancestorsAttributes = $this->categoryAttributeService
//             ->getAncestorsAttributes($category));
//         foreach ($ancestorsAttributes as $attribute) {
//             dump('ancestorsAttributes: ' . $attribute->id . ' - ' . $attribute->name);
//         }
//         echo '<hr>';

//         ($inheritedAttributesExcluded = $category->inheritedAttributesExcluded);
//         foreach ($inheritedAttributesExcluded as $attribute) {
//             dump('inheritedAttributesExcluded: ' . $attribute->id . ' - ' . $attribute->name);
//         }
//         echo '<hr>';

//         ($ancestorsAttributesExcluded = $this->categoryAttributeService
//             ->getAncestorsAttributesExcluded($category));
//         foreach ($ancestorsAttributesExcluded as $attribute) {
//             dump('ancestorsAttributesExcluded: ' . $attribute->id . ' - ' . $attribute->name);
//         }
//         echo '<hr>';

//         ($ancestorsAndSelfAttributesExcluded = $this->categoryAttributeService
//             ->getAncestorsAndSelfAttributesExcluded($category));
//         foreach ($ancestorsAndSelfAttributesExcluded as $attribute) {
//             dump('ancestorsAndSelfAttributesExcluded: ' . $attribute->id . ' - ' . $attribute->name);
//         }
//         echo '<hr>';

//         ($availableAncestorsAttributes = $this->categoryAttributeService
//             ->getAvailableAncestorsAttributes($category));
//         foreach ($availableAncestorsAttributes as $attribute) {
//             dump('availableAncestorsAttributes: ' . $attribute->id . ' - ' . $attribute->name);
//         }
//         echo '<hr>';

//         ($availableAncestorsAndSelfAttributes = $this->categoryAttributeService
//             ->getAvailableAncestorsAndSelfAttributes($category));
//         foreach ($availableAncestorsAndSelfAttributes as $attribute) {
//             dump('availableAncestorsAndSelfAttributes: ' . $attribute->id . ' - ' . $attribute->name);
//         }
//         echo '<hr>';

//         $allAvailableAttributes = $this->categoryAttributeService
//             ->getAllAvailableAttributes($category, $action);
//         foreach ($allAvailableAttributes as $attribute) {
//             dump('allAvailableAttributes: ' . $attribute->id . ' - ' . $attribute->name);
//         }
//         echo '<hr>';

//         $allAttributesExcluded = $this->categoryAttributeService->getAllAttributesExcluded($category, $action);
//         foreach ($allAttributesExcluded as $attribute) {
//             dump('allAttributesExcluded: ' . $attribute->id . ' - ' . $attribute->name);
//         }
//         echo '<hr>';

//         $requiredAttributes = $this->categoryAttributeService
//             ->getRequiredAttributes($availableAncestorsAndSelfAttributes, $action);
//         foreach ($requiredAttributes as $attribute) {
//             dump('requiredAttributes: ' . $attribute->id . ' - ' . $attribute->name);
//         }
//         echo '<hr>';

//         $columnAttributes = $this->categoryAttributeService
//             ->getColumnAttributes($availableAncestorsAndSelfAttributes, $action);
//         foreach ($columnAttributes as $attribute) {
//             dump('columnAttributes: ' . $attribute->id . ' - ' . $attribute->name);
//         }
//         echo '<hr>';

//         $excludedAttributesForAction = $this->categoryAttributeService
//             ->getExcludedAttributesForAction($availableAncestorsAndSelfAttributes, $action);
//         foreach ($excludedAttributesForAction as $attribute) {
//             dump('excludedAttributesForAction: ' . $attribute->id . ' - ' . $attribute->name);
//         }
//         echo '<hr>';

//         return 1;

// }
