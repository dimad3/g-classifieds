<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Adverts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Categories\CategoryRequest;
use App\Http\Requests\Admin\ImportFromFileRequest;
use App\Imports\AttributesImport;
use App\Imports\CategoriesImport;
use App\Models\Action\Action;
use App\Models\Action\ActionAttributeSetting;
use App\Models\Adverts\Attribute;
use App\Models\Adverts\Category;
use App\Services\Adverts\CategoryAttributeService;
use App\Services\Adverts\CategoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class CategoryController extends Controller
{
    protected CategoryService $categoryService;

    protected CategoryAttributeService $categoryAttributeService;

    public function __construct(CategoryService $categoryService, CategoryAttributeService $categoryAttributeService)
    {
        $this->categoryService = $categoryService;
        $this->categoryAttributeService = $categoryAttributeService;
        // $this->middleware('can:manage-adverts-categories'); // is called twice see "barryvdh/laravel-debugbar": gates. Is applyed also in: resources\views\admin\_nav.blade.php
    }

    public function index()
    {
        // https://packagist.org/packages/kalnoy/nestedset
        /**
         * whereIsRoot() - Scope limits query to select just root node.
         *
         * @return $this
         */

        /**
         * getModels() - Get the hydrated models without eager loading.
         *
         * @param  array|string  $columns
         * @return \Illuminate\Database\Eloquent\Model[]|static[]
         *                                                        vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php
         */
        $categories = Category::whereIsRoot()
            ->orderBy('sort')
            ->orderBy('name')
            ->with([
                // 'children',
                // 'children.children',
                'children' => function ($query): void {
                    $query->orderBy('sort')
                        ->orderBy('name')
                        ->with('children');
                },
            ])
            ->paginate(40);

        return view('admin.adverts.categories.index', compact('categories'));
    }

    public function getSubCategories(Category $parentCategory)
    {
        $categories = $parentCategory
            ->children()
            ->orderBy('sort')
            ->orderBy('name')
            ->with([
                // 'children',
                // 'children.children',
                'children' => function ($query): void {
                    $query->orderBy('sort')
                        ->orderBy('name')
                        ->with('children');
                },
            ])
            ->paginate(50);

        // dump($categories);
        // return 1;

        return view('admin.adverts.categories.index', compact('categories', 'parentCategory'));
    }

    /**
     * Create Root Category
     */
    public function create()
    {
        return $this->edit(new Category());
    }

    /**
     * Create Sub Category
     */
    public function createSubCategory(Category $parentCategory)
    {
        return $this->edit(new Category(), $parentCategory);
    }

    /**
     * Store Root Category
     */
    public function store(CategoryRequest $request)
    {
        return $this->update($request, new Category());
    }

    /**
     * Store Sub Category
     */
    public function storeSubCategory(CategoryRequest $request, Category $parentCategory)
    {
        return $this->update($request, new Category(), $parentCategory->id);
    }

    public function show(Category $category)
    {
        /**
         * @var Collection|Category[] $ancestors
         *                            A collection of ancestors categories only for displaying in the view.
         */
        ($ancestors = $category->ancestors);
        /**
         * @var Collection|Category[] $subCategories
         *                            A collection of subCategories only for displaying in the view.
         */
        $subCategories = $category->children()->orderBy('sort')->orderBy('name')->get();

        // Set actions variables ================================

        /** @var Collection $actions Assignead actions which belong to this specific category. */
        ($actions = $category->getAssignedActions($category));

        /** @var Collection $ancestorsActions available inherited actions (belong to this category's ancestors). */
        ($ancestorsActions = $category->getAdjustedActions($ancestors)->loadMissing('category'));

        /** @var Collection $ancestorsActionsToBeExcluded inherited actions (belong to this category's ancestors)
         * which have been checked as excluded (). */
        ($ancestorsActionsToBeExcluded = $category->actionsExcluded);

        /**
         * @var bool $actionsCanBeAssigned - Flag indicating whether the [Assign Actions] and [All Actions & Attributes Settings]
         *           buttons should be displayed.
         */
        $actionsCanBeAssigned = true;
        // if ($category->descendantsOrMyAttributesHaveSettingsWithoutActions()) {  // commented on 24.06.2024
        // if ($category->hasSettingsForAttributesWithoutActions()) {  // commented on 26.06.2024 - see bug description #1
        if ($category->ancestorsOrMyAttributesHaveSettingsWithoutActions()) {
            $actionsCanBeAssigned = false;
        }

        /**
         * @var bool $attributeSettingsCanBeAssigned - display attribute's checkboxes (Required, Column) & [Save Settings] button,
         */
        $allAncestorsActionsAreExcluded = $category->allAncestorsActionsAreExcluded();
        $attributeSettingsCanBeAssigned = false;
        if (! $category->descendantsOrMeHaveActions() && $allAncestorsActionsAreExcluded) {
            $attributeSettingsCanBeAssigned = true;
        }

        // Set attributes variables ================================

        /**
         * @var Collection|Attribute[] $ancestorsAndSelfAttributes
         *                             A collection of Attributes associated with the given category and its ancestors.
         *                             This is used exclusively within this method to fetch data from it instead of querying the db again.
         */
        ($ancestorsAndSelfAttributes = $this->categoryAttributeService->getAncestorsAndSelfAttributes($category));
        ($ancestorsAttributes = $ancestorsAndSelfAttributes->where('category_id', '!=', $category->id));
        ($attributes = $ancestorsAndSelfAttributes->where('category_id', $category->id));

        ($availableAncestorsAttributes = $this->categoryAttributeService->getAvailableAncestorsAttributes($category)
            // we need to load `category` relation to show attribute category in the ancestors attributes table
            ->loadMissing('category'));

        /**
         * @var Collection|Attribute[] $ancestorsAttributesToBeExcluded
         *                             A collection of ancestors' attributes from the `advert_category_inherited_attributes_exclusions` table
         *                             that will be excluded (cannot be assigned to an advert of this category).
         *                             It is used exclusively in the subview "_attributes_table.blade"
         *                             to determine if an ancestor's attribute is excluded (checkbox is set to 'checked').
         */
        ($ancestorsAttributesToBeExcluded = $this->categoryAttributeService
            ->getAncestorsAndSelfAttributesExcluded($category));

        ($requiredAttributes = $this->categoryAttributeService->getRequiredAttributes($attributes));
        ($columnsAttributes = $this->categoryAttributeService->getColumnAttributes($attributes));

        return view(
            'admin.adverts.categories.show',
            compact(
                'category',
                'ancestors',
                'subCategories',
                'actions', // Assignead actions which belong to this specific category
                'ancestorsActions',
                'ancestorsActionsToBeExcluded',
                'actionsCanBeAssigned',
                'attributeSettingsCanBeAssigned',
                'allAncestorsActionsAreExcluded',
                'attributes',
                'availableAncestorsAttributes',
                'requiredAttributes',
                'columnsAttributes',
                'ancestorsAttributesToBeExcluded'
            )
        );
    }

    /**
     * Show the form for editing or creating new category.
     *
     * @param  Category  $category  for editing it is category, for creating new category it is new Category()
     * @param  Category  $parentCategory  [optional] - it is Parent Category only for creating new Sub Category
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(Category $category, ?Category $parentCategory = null)
    {
        // Cache for parent_id dropdown for editing category ONLY, when creating new category dropdown is not needed
        if ($category->exists) {
            if (Cache::missing('categoriesPaths')) {
                // run only when 'categoriesPaths' are not set in Cache, that is highly unlikely
                $this->categoryService->RebuildCategoriesPaths();
            }
            $categoriesPaths = (array) Cache::get('categoriesPaths');

            // exclude self & descendants from array
            $descendants = Category::descendantsAndSelf($category->id);
            foreach ($descendants as $descendant) {
                $categoriesPaths = array_filter($categoriesPaths, function ($item) use ($descendant) {
                    return $item['id'] !== $descendant->id;
                });
            }
        }
        isset($categoriesPaths) ?: $categoriesPaths = [];

        return view('admin.adverts.categories.create_or_edit', compact(
            'category',
            'parentCategory',
            'categoriesPaths',   // for parent_id dropdown for editing category only
        ));
    }

    public function update(CategoryRequest $request, Category $category, ?int $parentCategoryId = null)
    {
        $this->categoryService->storeOrUpdate($request, $category, $parentCategoryId);

        return redirect()->route('admin.adverts.categories.show', $category);
    }

    /**
     * Remove the category from storage.
     */
    public function destroy(Category $category)
    {
        if ($category->children()->exists()) {
            return redirect()
                ->back()
                ->with(
                    'error',
                    'You can not delete category which has subcategories.<br>
                    At first delete all sub categories!'
                );
        } elseif ($category->adverts()->exists()) {
            return redirect()
                ->back()
                ->with(
                    'error',
                    "You can not delete the category which has adverts!<br>
                    At first delete all adverts which are assigned to this category: [{$category->getPath()}].<br>
                    Then try again."
                );
        } elseif ($category->categoryAttributes()->exists()) {
            return redirect()
                ->back()
                ->with(
                    'error',
                    "You can not delete the category which has assigned attributes!<br>
                    At first delete all attributes which are assigned to this category: [{$category->getPath()}].<br>
                    Then try again."
                );
        }
        // $parentCategory for redirection after delete
        // $parentCategory = Category::find($category->parent_id);
        $parentCategory = $category->parent;
        ($this->categoryService->destroy($category));

        if ($parentCategory) {
            return redirect()->route('admin.adverts.categories.subcats.index', $parentCategory)->with('success', "Category: [{$category->name}] was deleted!");
        }

        return redirect()->route('admin.adverts.categories.index')->with('success', "Category: [{$category->name}] was deleted!");
    }

    public function selectActions(Category $category)
    {
        // bug: why ancestorsAndSelf() produce wrong sequence of categories: Drēbes, apavi / Aksesuāri, rotaslietas / Aproces / Dārglietas
        // ($categoryAndItsAncestors = $category->ancestorsAndSelf($category->id));
        ($categoryAndItsAncestors = $category->ancestorsAndMe());

        // if actions are assigned to ancestors' or descendants' categories do NOT display them to avoid duplicate records in action_attribute_settings table
        // bug: https://stackoverflow.com/questions/52312270/laravel-collection-push-method-unknown-behaviour
        // dd($category->ancestors);    /return ancestors + Me
        // workaround
        // ($actionsIds = $category->getAssignedActions($categoryAndItsAncestors->where('id', '<>', $category->id))->pluck('id')->concat($category->getAssignedActions($category->descendants)->pluck('id')));
        ($actionsIds = $category->getAssignedActions($category->ancestors)->pluck('id')->concat($category->getAssignedActions($category->descendants)->pluck('id')));
        ($actions = $category->getAllActions()->whereNotIn('id', $actionsIds));

        ($selectedActions = $category->actions);
        // dump($selectedActions, $selectedActions->pluck('id')->toArray(), $actions);
        // return 1;

        return view(
            'admin.adverts.categories.create_action_category',
            compact('category', 'categoryAndItsAncestors', 'actions', 'selectedActions')
        );
    }

    public function storeActions(Request $request, Category $category)
    {
        // dd($request['actions']);

        // only actions which are not assigned to ancestors' or descendants' categories can be assigned (stored) to avoid duplicate records in action_attribute_settings table
        ($actionsIds = $category->getAssignedActions($category->ancestors)->pluck('id')->concat($category->getAssignedActions($category->descendants)->pluck('id')));
        ($actions = Action::all()->whereNotIn('id', $actionsIds));    // for validation only

        $validated = $request->validate([
            // https://laravel.com/docs/10.x/validation#rule-array
            // https://laravel.com/docs/10.x/validation#validating-nested-array-input
            'actions' => ['array'],
            'actions.*' => ['array:actionId,sort'],
            'actions.*.actionId' => ['numeric', 'integer', 'distinct', Rule::in($actions->modelKeys())],
            'actions.*.sort' => ['required', 'numeric', 'integer', 'min:0', 'max:255'],
        ]);
        // return 1;

        // filter array to only include selected actions
        ($selectedActions = array_filter($request['actions'], function ($action) {
            // dd($item);
            return array_key_exists('actionId', $action);
        }));

        // prepeare array to sync data in action_category table
        // https://stackoverflow.com/questions/27230672/laravel-sync-how-to-sync-an-array-and-also-pass-additional-pivot-fields/27230803#27230803
        $actions = [];
        foreach ($selectedActions as $action) {
            $actions[$action['actionId']] = [
                'sort' => $action['sort'],
                'excluded' => false,
            ];
            // $actions[$action['actionId']] = ['excluded' => $action['sort']];
        }
        //  dd($actions);

        DB::transaction(function () use ($category, $actions): void {
            $category->actions()->sync($actions);

            // if action for category was deleted -> remove its settings
            // todo: add id to action_category table -> then use cascade delete from settings table
            ($actionsUpdated = $category->getAssignedActions($category->ancestorsAndMe())->concat($category->getAssignedActions($category->descendants)));
            // ($actionsUpdated = $category->ancestorsAndMyActions()); // bad idea because if actions for descendants categories are assigned their settings will be removed also
            // ($actionsUpdated = $category->actions); // bad idea because all actions' settings (including ancestors settings) will be removed

            // ($settings = $category->settings);  // bad idea because on action delete - only this category settings will be removed. But category descendants assigned settings remain
            ($settings = $category->ancestorsAndMySettings()->merge($category->descendantsSettings()));

            // if category does not have actions -> delete all settings
            if ($actionsUpdated->isEmpty()) {
                // delete all settings
                ActionAttributeSetting::destroy($settings);
            } else {
                // if category has actions -> delete settings without action
                foreach ($settings as $setting) {
                    // dump(!$actionsUpdated->contains('id', $setting->action_id));
                    if (! $actionsUpdated->contains('id', $setting->action_id)) {
                        //  dump($setting->action_id);
                        $setting->delete();
                    }
                }
            }
        });

        return redirect()->route('admin.adverts.categories.show', $category);
    }

    /**
     * Store attribute settings for attributes which category and its ancestors do NOT have assigned actions
     */
    public function storeSettings(Request $request, Category $category)
    {
        ($attributes = $category->categoryAttributes);  // for validation only
        $validatedData = $request->validate([
            // todo: each key in the input array must be present within the list of values provided to the rule
            // https://laravel.com/docs/10.x/validation#rule-array
            // https://laravel.com/docs/10.x/validation#validating-nested-array-input
            'requiredAttributes' => ['array'],
            'requiredAttributes.*' => ['required', 'numeric', 'integer', 'distinct', Rule::in($attributes->modelKeys())],

            'columnsAttributes' => ['array'],
            'columnsAttributes.*' => ['required', 'numeric', 'integer', 'distinct', Rule::in($attributes->modelKeys())],
        ]);
        // dd($validatedData);
        // dd(empty($validatedData));
        // $myAttributes = $category->categoryAttributes;

        ($requiredIds = data_get($validatedData, 'requiredAttributes', []));
        ($columnIds = data_get($validatedData, 'columnsAttributes', []));
        ($allSettingsIds = array_unique(array_merge($requiredIds, $columnIds)));
        // return 1;

        DB::transaction(function () use ($attributes, $allSettingsIds, $requiredIds, $columnIds): void {
            ($deleted = ActionAttributeSetting::query()
                ->where('action_id', null)
                ->whereIn('attribute_id', $attributes->modelKeys())
                ->delete());
            // todo: run new settings inserts only if all records were deleted succesfuly
            foreach ($attributes as $attribute) {
                // save setting only for attribute which is in allSettingsIds array
                if (in_array($attribute->id, $allSettingsIds)) {
                    $attributeSetting = new ActionAttributeSetting();
                    $attributeSetting->action_id = null;
                    $attributeSetting->attribute_id = $attribute->id;
                    $attributeSetting->required = false;
                    $attributeSetting->column = false;
                    $attributeSetting->excluded = false;

                    if (in_array($attribute->id, $requiredIds)) {
                        $attributeSetting->required = true;
                    }

                    if (in_array($attribute->id, $columnIds)) {
                        $attributeSetting->column = true;
                    }
                    // dump($attributeSetting);
                    $attribute->setting()->save($attributeSetting);
                }
            }
        });

        // return 1;
        // return redirect()->route('admin.adverts.categories.show', $category);
        return redirect()->back()->with('success', 'Setings were saved successfully!');
    }

    /**
     * Store excluded actions in `action_category` table
     */
    public function storeExcludedActions(Request $request, Category $category)
    {
        // if ($category->descendantsOrMyAttributesHaveSettingsWithoutActions()) {  // commented on 24.06.2024
        if ($category->hasSettingsForAttributesWithoutActions()) {
            throw new \DomainException(
                "To avoid data conflicts, it is prohibited to change excluded actions for this category because
                this category ({$category->name}) or its descendants have attributes which have assigned settings without actions!"
            );     // put the error in the session
        }

        ($actions = $category->getAssignedActions($category->ancestors));  // for validation only
        $validatedData = $request->validate([
            // todo: each key in the input array must be present within the list of values provided to the rule
            // https://laravel.com/docs/10.x/validation#rule-array
            // https://laravel.com/docs/10.x/validation#validating-nested-array-input
            'ancestorsActionsToBeExcluded' => ['array'],
            'ancestorsActionsToBeExcluded.*' => ['required', 'numeric', 'integer', 'distinct', Rule::in($actions->modelKeys())],
        ]);
        // dd($validatedData);

        ($excludedIds = data_get($validatedData, 'ancestorsActionsToBeExcluded', []));

        $category->actionsExcluded()->syncWithPivotValues($excludedIds, ['excluded' => true]);

        return redirect()->back()->with('success', 'Excluded Actions were updated successfully!');
    }

    /**
     * Store excluded attributes in `advert_category_inherited_attributes_exclusions` table
     */
    public function storeExcludedAttributes(Request $request, Category $category)
    {
        // dd($request->input());
        ($attributes = $this->categoryAttributeService->getAvailableAncestorsAttributes($category));  // for validation only
        $validatedData = $request->validate([
            // todo: each key in the input array must be present within the list of values provided to the rule
            // https://laravel.com/docs/10.x/validation#rule-array
            // https://laravel.com/docs/10.x/validation#validating-nested-array-input
            'ancestorsAttributesToBeExcluded' => ['array'],
            'ancestorsAttributesToBeExcluded.*' => ['required', 'numeric', 'integer', 'distinct', Rule::in($attributes->modelKeys())],
        ]);
        // dd($validatedData);

        ($excludedIds = data_get($validatedData, 'ancestorsAttributesToBeExcluded', []));

        $category->inheritedAttributesExcluded()->sync($excludedIds);

        return redirect()->back()->with('success', 'Excluded Attributes were updated successfully!');
    }

    public function subCategoriesesImport(ImportFromFileRequest $importFromFileRequest, Category $parentCategory)
    {
        Excel::import(new CategoriesImport(), $importFromFileRequest->file('file'));
        // todo: why return redirect()->back() run only after RebuildCategoriesPaths() is finished (after ~10 sec.)?
        $this->categoryService->RebuildCategoriesPaths();

        return redirect()->back()->with('success', 'Sub Categories have been imported successfuly!');
    }

    public function attributesImport(ImportFromFileRequest $importFromFileRequest, Category $category)
    {
        Excel::import(new AttributesImport(), $importFromFileRequest->file('file'));

        return redirect()->route('admin.adverts.categories.show', $category)->with('success', 'Sub Categories have been imported successfuly!');
    }
}
