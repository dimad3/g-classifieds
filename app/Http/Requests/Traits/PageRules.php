<?php

declare(strict_types=1);

namespace App\Http\Requests\Traits;

use App\Models\Page;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\NotIn;
use Illuminate\Validation\Rules\Unique;

/*
|--------------------------------------------------------------------------
| Trait PageRules
|--------------------------------------------------------------------------
|
| Provides common validation rules and helper methods for page-related requests,
| including rules for creating and updating pages.
| Ensures that pages maintain a valid hierarchy without circular dependencies.
|
*/

trait PageRules
{
    /**
     * Returns common validation rules for both 'POST' and 'PUT' requests.
     *
     * @return array The base validation rules for page fields.
     */
    protected function commonPageRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],  // 'title' is required and must be a string with a max length of 255.
            'menu_title' => ['required', 'string', 'max:255'],  // 'menu_title' is required and must be a string with a max length of 255.
            'slug' => ['string', 'max:255'],               // 'slug' is optional but must be a string with a max length of 255.
            'sort' => ['required', 'numeric', 'integer', 'min:0', 'max:255'],  // 'sort' is required, must be numeric, and within the specified range.
            'parent_id' => ['nullable', 'numeric', 'integer', 'exists:pages,id'],  // 'parent_id' is optional, must be numeric, and must reference an existing page ID.
            'description' => ['nullable', 'string'],  // 'description' is nullable and must be a string.
            'content' => ['required', 'string'],  // 'content' is required and must be a string.
        ];
    }

    /**
     * Combines common rules with additional rules based on the request method and input data.
     *
     * @param  array  $data  The input data to be validated.
     * @return array The validation rules for page creation/updating.
     */
    protected function allPageRules(array $data): array
    {
        $rules = $this->commonPageRules(); // Get the base validation rules.
        $this->applyExtraRules($rules, $data); // Apply extra rules based on the input data.

        // Debugging output to check the input data and modified rules (remove in production).
        // dd($data, $rules);

        return $rules;
    }

    /**
     * Applies additional validation rules based on the context of the parent page and request type.
     *
     * @param  array  $rules  The current rules to be modified.
     * @param  array  $data  The input data to be validated.
     * @return void Modifies the $rules array by reference.
     */
    private function applyExtraRules(array &$rules, array $data): void
    {
        // Determine the parent ID from the input data, defaulting to null if not set.
        $parentId = array_key_exists('parent_id', $data) ? (int) $data['parent_id'] : null;

        // Apply uniqueness rule for 'title' if present in the input data.
        if (array_key_exists('title', $data)) {
            $rules['title'][] = $this->setRuleUnique($parentId);
        }

        // Apply uniqueness rule for 'menu_title' if present in the input data.
        if (array_key_exists('menu_title', $data)) {
            $rules['menu_title'][] = $this->setRuleUnique($parentId);
        }

        // Apply uniqueness rule for 'slug' if present in the input data, and make it required for updates.
        if (array_key_exists('slug', $data)) {
            $rules['slug'][] = $this->setRuleUnique($parentId);
            if ($this->isMethod('PUT')) {
                $rules['slug'][] = 'required'; // 'slug' is required for updates.
            }
        }

        // Apply a rule to prevent circular hierarchies for 'parent_id' on updates.
        if (array_key_exists('parent_id', $data) && $this->isMethod('PUT')) {
            $rules['parent_id'][] = $this->setRuleNotInDescendantsAndSelfIds();
        }
    }

    /**
     * Builds a uniqueness validation rule for a specified field based on request type
     * and parent context.
     *
     * @param  int|null  $parentId  The parent page ID, or null for root pages.
     * @param  string|null  $column  (optional) Specify if the column in the table differs from the field name.
     * @return Unique The uniqueness rule for validation.
     */
    private function setRuleUnique(?int $parentId, ?string $column = 'NULL'): Unique
    {
        $rule = Rule::unique('pages', $column) // Specify the 'pages' table and optional column.
            ->where('parent_id', $parentId); // Enforce uniqueness within the context of the parent page.

        if ($this->isMethod('PUT')) {
            $rule->ignore($this->page); // Ignore the current page when checking for uniqueness on updates.
        }

        return $rule; // Return the constructed uniqueness rule.
    }

    /**
     * Prevents circular hierarchy by ensuring 'parent_id' is not equal to the page's ID
     * and is not one of the page's descendants' IDs.
     *
     * @return NotIn A rule ensuring the parent ID is not one of the excluded IDs.
     */
    private function setRuleNotInDescendantsAndSelfIds(): NotIn
    {
        // Check if the page exists before retrieving its descendants.
        if ($this->page->exists) {
            // Retrieve all descendant IDs including the page itself.
            $excludedParentIds = Arr::flatten($this->page->descendantsAndSelf($this->page->id, ['id'])->toArray());

            // Return a rule that the parent_id must not be in the excluded IDs list.
            return Rule::notIn($excludedParentIds);
        }

        // If the page does not exist, no rule is applied (optional handling).
        return Rule::notIn([]); // Return a rule that allows any parent_id when page does not exist.
    }
}
