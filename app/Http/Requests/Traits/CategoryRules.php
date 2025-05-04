<?php

declare(strict_types=1);

namespace App\Http\Requests\Traits;

use App\Models\Adverts\Category;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait CategoryRules
{
    /**
     * Returns the common validation rules for categories, applicable
     * for both 'POST' and 'PUT' requests.
     *
     * @return array The validation rules for category fields.
     */
    protected function commonCategoryRules(): array
    {
        // General validation rules for creating or editing a category
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['string', 'max:255'],
            'sort' => ['required', 'numeric', 'integer', 'min:0', 'max:255'],
            'parent_id' => ['nullable', 'numeric', 'integer', 'min:1', 'max:4294967295', 'exists:advert_categories,id'],
        ];

        return $rules;
    }

    /**
     * Add extra rules to Common Rules depending on some conditions
     *
     * @param  array  $data  The input data to be validated.
     * @param  bool  $isActionUpdate  True if the action is an update ('PUT'), false if it's a store ('POST').
     * @return array The validation rules tailored for the request.
     */
    protected function allCategoryRules(array $data, bool $isActionUpdate): array
    {
        // Apply different rules depending on whether it's a 'POST' or 'PUT' request.
        $rules = $isActionUpdate ? $this->categoryRulesOnPut($data) : $this->categoryRulesOnPost($data);

        return $rules;
    }

    /**
     * Adds additional rules specific to 'POST' requests, including
     * unique checks for 'name' and 'slug' fields based on parent category context.
     *
     * @param  array  $data  The input data to be validated.
     * @return array The enhanced rules for category validation on 'POST'.
     *
     * @throws \DomainException If attempting to move a category into itself or its descendant.
     */
    private function categoryRulesOnPost(array $data): array
    {
        // Start with the common category rules
        $rules = $this->commonCategoryRules();

        // Add a uniqueness rule for 'name' within the parent category's context
        array_push(
            $rules['name'], // Append uniqueness rule for 'name'
            $this->ruleUniqueOnPost($this->parentCategory)
        );

        // If 'slug' is provided, make it required and ensure its uniqueness within the parent category's context
        if (array_key_exists('slug', $data)) {
            array_unshift($rules['slug'], 'required'); // Add 'required' to the beginning of the rules for 'slug'.
            array_push($rules['slug'], $this->ruleUniqueOnPost($this->parentCategory)); // Ensure slug uniqueness.
        }

        // Return the fully constructed rules array, which is now specific to the POST request.
        return $rules;
    }

    /**
     * Adds additional rules specific to 'PUT' requests, including uniqueness checks
     * for 'name' and 'slug' fields, while ensuring the category is not a descendant of itself.
     *
     * @param  array  $data  The input data to be validated.
     * @return array The enhanced rules for category validation on 'PUT'.
     */
    private function categoryRulesOnPut(array $data): array
    {
        // Start with common category rules
        $rules = $this->commonCategoryRules();

        // If 'name' is provided, check that it is unique within the parent category, excluding the current category itself
        if (array_key_exists('name', $data)) {
            array_push(
                $rules['name'],
                Rule::unique('advert_categories')
                    ->where('parent_id', $data['parent_id'] ?? null) // Apply uniqueness rule in the context of the parent category
                    ->ignore($this->category)  // Ignore the current category when checking for uniqueness
            );
        }

        // If 'slug' is provided, make it required and check that it is unique within the parent category, excluding the current category itself
        if (array_key_exists('slug', $data)) {
            $rules['slug'] = ['required', 'string', 'max:255']; // Ensure 'slug' is required and valid
            array_push(
                $rules['slug'],
                Rule::unique('advert_categories')
                    ->where('parent_id', $data['parent_id'] ?? null) // Apply uniqueness rule in the context of the parent category
                    ->ignore($this->category)  // Ignore the current category when checking for uniqueness
            );
        }

        // Validate that 'parent_id' is not one of the current category's descendants or itself.
        // This ensures it's impossible for the 'parent_id' to be equal to any of the current category's
        // descendants or the category itself, preventing the creation of invalid category hierarchies.
        if (array_key_exists('parent_id', $data)) {
            // Retrieve all IDs of the category's descendants, including the category itself, as an array.
            $excludedParentIds = Arr::flatten($this->category->descendantsAndSelf($this->category->id, ['id'])->toArray());

            // Append a rule to ensure that 'parent_id' is not in the list of excluded IDs.
            // This prevents setting the parent as one of its own descendants, which would create a circular hierarchy.
            array_push($rules['parent_id'], Rule::notIn($excludedParentIds));
        }

        return $rules;
    }

    /**
     * Builds a uniqueness validation rule for the category 'name' and 'slug' fields
     * based on the context of the parent category.
     *
     * @param  Category|null  $parentCategory  The parent category, or null if the category is root-level.
     * @return Unique The uniqueness rule for validating the category.
     */
    private function ruleUniqueOnPost(?Category $parentCategory): Unique
    {
        // Return a unique rule that enforces uniqueness within the parent category context
        return Rule::unique('advert_categories')
            ->where(function ($query) use ($parentCategory) {
                // If the category has a parent, ensure uniqueness within its subcategories
                if ($parentCategory) {
                    return $query->where('parent_id', $parentCategory->id);
                }

                // If the category is root-level, ensure uniqueness among root categories
                return $query->where('parent_id', null);
            });
    }
}
