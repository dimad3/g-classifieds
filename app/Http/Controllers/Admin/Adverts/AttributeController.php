<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Adverts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Categories\ActionAttributeRequest;
use App\Http\Requests\Admin\Categories\AttributeRequest;
use App\Models\Adverts\Attribute;
use App\Models\Adverts\Category;
use App\Services\Adverts\CategoryAttributeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttributeController extends Controller
{
    protected CategoryAttributeService $categoryAttributeService;

    public function __construct(CategoryAttributeService $categoryAttributeService)
    {
        $this->categoryAttributeService = $categoryAttributeService;
        // $this->middleware('can:manage-adverts-categories'); // is called twice see "barryvdh/laravel-debugbar": gates. Is applyed also in: resources\views\admin\_nav.blade.php
    }

    public function create(Category $category)
    {
        return $this->edit($category, new Attribute());
    }

    public function store(AttributeRequest $request, Category $category)
    {
        return $this->update($request, $category, new Attribute());
    }

    public function show(Category $category, Attribute $attribute)
    {
        // todo & bug: why ancestorsAndSelf() produce wrong sequence of categories: Drēbes, apavi / Aksesuāri, rotaslietas / Aproces / Dārglietas
        // ($categoryAndItsAncestors = $category->ancestorsAndSelf($category->id));

        // is it bug, is this behavior ok (see questions in ads2-notes.doc):
        // dump($category);    // no `ancestors` relations
        // dump($categoryAndItsAncestors = $category->ancestorsAndMe());
        // dd($category);  // `ancestors` relations are set. Why? Is it bug?
        // $category->ancestors -> `ancestors` relations are set.
        // $category->ancestors()->get() -> no `ancestors` relations
        ($categoryAndItsAncestors = $category->ancestorsAndMe());
        ($actionsOfCategory = $category->getAssignedActions($category));
        // dd($category);  // `allActions` relations are set. Why?

        ($ancestorsActions = $category->getAdjustedActions($categoryAndItsAncestors)->except($actionsOfCategory->modelKeys()));

        ($actionsRequired = $attribute->actionsForWhichIAmRequired);
        ($actionsColumn = $attribute->actionsForWhichIAmColumn);
        ($actionsExcluded = $attribute->actionsForWhichIWillBeExcluded);

        /**
         * @var bool $settingsCanBeAssigned - if false: hide actions checkboxes & [Save Settings] button
         */
        $settingsCanBeAssigned = true;
        // if ($category->descendantsOrMyAttributesHaveSettingsWithoutActions()) {  // commented on 24.06.2024
        if ($category->hasSettingsForAttributesWithoutActions()) {
            $settingsCanBeAssigned = false;
        }

        return view(
            'admin.adverts.categories.attributes.show',
            compact(
                'categoryAndItsAncestors',
                'category',
                'attribute',
                'actionsOfCategory',
                'ancestorsActions',
                'actionsRequired',
                'actionsColumn',
                'actionsExcluded',
                'settingsCanBeAssigned'
            )
        );
    }

    public function edit(Category $category, Attribute $attribute)
    {
        $types = Attribute::typesList();

        return view('admin.adverts.categories.attributes.create_or_edit', compact('category', 'attribute', 'types'));
    }

    public function update(AttributeRequest $request, Category $category, Attribute $attribute)
    {
        $request->storeOrUpdate($category, $attribute);

        return redirect()->route('admin.adverts.categories.show', $category);
    }

    public function destroy(Category $category, Attribute $attribute)
    {
        if ($attribute->adverts()->exists()) {
            return redirect()
                ->back()
                ->with(
                    'error',
                    "You can not delete the attribute which has assigned to adverts!<br>
                    At first delete all adverts which have this attribute: [{$attribute->category->name} -> {$attribute->name}].<br>
                    Then try again."
                );
        }
        $attribute->delete();

        return redirect()->route('admin.adverts.categories.show', $category)
            ->with('success', "Attribute: [{$attribute->category->name} -> {$attribute->name}] was deleted!");
    }

    /**
     * This method displays the settings for all attributes of the given category.
     * is used in the admin interface for configuring attribute and action settings for a category,
     * including specifying attributes that are required, used as columns in the adverts table, or excluded.
     * It handles fetching the relevant attributes and actions for both the category and its ancestors,
     * along with their associated settings.
     * The data is then passed to a view, where it is presented in a form for further editing.
     */
    public function selectSettingsForAllAttributes(Category $category)
    {
        // todo & bug: why ancestorsAndSelf() produce wrong sequence of categories: Drēbes, apavi / Aksesuāri, rotaslietas / Aproces / Dārglietas
        // ($categoryAndItsAncestors = $category->ancestorsAndSelf($category->id));

        // for displaying category's path (as title of table)
        // Retrieve the category along with its ancestors
        ($categoryAndItsAncestors = $category->ancestorsAndMe()); // Kalnoy\Nestedset\Collection{App\Models\Adverts\Category}

        // Load all avialable attributes of the provided category along with relevant settings (required, column, excluded).
        ($avialableAttributes = $this->categoryAttributeService->getAllAvailableAttributes($category)
            ->loadMissing('category', 'actionsForWhichIAmRequired', 'actionsForWhichIAmColumn', 'actionsForWhichIWillBeExcluded'));

        ($actions = $category->getAdjustedActions($categoryAndItsAncestors));

        // Return the view that displays the form for configuring all pivot-related attribute/action settings.
        return view('admin.adverts.categories.create_all_attributes_settings', compact('category', 'categoryAndItsAncestors', 'avialableAttributes', 'actions'));
    }

    /**
     * Store all actions' settings for attribute
     */
    public function storeSettings(Request $request, Category $category, Attribute $attribute)
    // public function storeSettings(Request $request, Attribute $attribute)
    {
        // dump($request->input());
        // dump($category);
        ($actions = $category->getAdjustedActions($category->ancestorsAndMe())); // for validation only
        $validatedData = $request->validate([
            // todo: each key in the input array must be present within the list of values provided to the rule
            // https://laravel.com/docs/10.x/validation#rule-array
            // https://laravel.com/docs/10.x/validation#validating-nested-array-input
            'actionsRequired' => ['array'],
            'actionsRequired.*' => ['required', 'numeric', 'integer', 'distinct', Rule::in($actions->modelKeys())],

            'actionsColumn' => ['array'],
            'actionsColumn.*' => ['required', 'numeric', 'integer', 'distinct', Rule::in($actions->modelKeys())],

            'actionsExcluded' => ['array'],
            'actionsExcluded.*' => ['required', 'numeric', 'integer', 'distinct', Rule::in($actions->modelKeys())],
        ]);
        // dd((empty($validatedData)));
        ($requiredIds = data_get($validatedData, 'actionsRequired', []));
        ($columnIds = data_get($validatedData, 'actionsColumn', []));
        ($excludedIds = data_get($validatedData, 'actionsExcluded', []));
        ($allSettingsIds = array_unique(array_merge($requiredIds, $columnIds, $excludedIds)));
        // return 1;

        $settings = [];
        foreach ($actions as $action) {
            // save setting only for action which is in allSettingsIds array
            if (in_array($action->id, $allSettingsIds)) {
                $setting = [];
                $setting['required'] = false;
                $setting['column'] = false;
                $setting['excluded'] = false;
                if (in_array($action->id, $requiredIds)) {
                    $setting['required'] = true;
                }

                if (in_array($action->id, $columnIds)) {
                    $setting['column'] = true;
                }

                if (in_array($action->id, $excludedIds)) {
                    $setting['excluded'] = true;
                }
                // dump($setting);
                $settings[$action->id] = $setting;
            }
        }
        // dd($settings);
        // https://stackoverflow.com/questions/27230672/laravel-sync-how-to-sync-an-array-and-also-pass-additional-pivot-fields/27230803#27230803
        $attribute->actions()->sync($settings);

        // return redirect()->route('admin.adverts.categories.attributes.show', [$category, $attribute]);
        return redirect()->back()->with('success', 'Settings were saved successfully!');
    }

    /**
     * Version 1 (tested on 26.09.2024 22:00 -> is ok (query count = ~40))
     * Store and update action settings for all attributes.
     * This method processes the incoming request, which contains settings for multiple attributes,
     * and updates the actions associated with each attribute accordingly. For each attribute, if the
     * ID is present in the request, the actions are updated with the provided settings. If the attribute's
     * ID is not included, it detaches all existing actions for that attribute.
     *
     * Actions of attribute may be attached with specific settings like 'required', 'column', or 'excluded'.
     *
     * @param  ActionAttributeRequest  $request  The incoming request containing attribute settings.
     * @param  Category  $category  The category for which the attributes are being updated.
     * @return \Illuminate\Http\RedirectResponse Redirect back with a success message.
     */
    public function storeSettingsForAllAttributes(ActionAttributeRequest $request, Category $category)
    {
        // dd($request->input('settings'));
        // dd($request->input());
        // Check if array of 'settings' exists in the request input
        if (array_key_exists('settings', $request->input())) {
            // Extract attribute IDs from the settings input. Only if attribute has at least one setting set.
            // Settings ($request->input('settings')) can be for attributes that belong to the current category,
            // as well as attributes from ancestor and descendant categories
            /**
             * @var array $attributesIdsFromRequest Array of attribute IDs and their settings from the request.
             */
            ($attributesIdsFromRequest = $request->input('settings'));
        } else {
            // No settings provided, initialize with an empty array
            $attributesIdsFromRequest = [];
        }

        /**
         * @var \Illuminate\Database\Eloquent\Collection|Attribute[] $ancestorsAndSelfAttributes
         *                                                           A collection of attributes associated with the category and its ancestors.
         */
        ($ancestorsAndSelfAttributes = $this->categoryAttributeService->getAncestorsAndSelfAttributes($category));
        /**
         * @var \Illuminate\Database\Eloquent\Collection|Attribute[] $categoryAttributes
         *                                                           A collection of attributes that belong specifically to the given category.
         */
        ($categoryAttributes = $ancestorsAndSelfAttributes->where('category_id', $category->id));
        // ($otherAttributesIds = $allAttributes->diff($categoryAttributes));
        /**
         * @var \Illuminate\Database\Eloquent\Collection|\App\Models\Action\Action[] $categoryActions
         *                                                                           A collection of adjusted actions for the category itself.
         */
        ($categoryActions = $category->getAdjustedActions($category));
        /**
         * @var \Illuminate\Database\Eloquent\Collection|\App\Models\Action\Action[] $allActions
         *                                                                           A collection of adjusted actions for the category and its ancestors.
         */
        ($allActions = $category->getAdjustedActions($category->ancestorsAndMe()));

        DB::transaction(function () use ($attributesIdsFromRequest, $ancestorsAndSelfAttributes, $categoryAttributes, $allActions, $categoryActions): void {
            // Loop through each attribute associated with the category
            // 23.09.2024 commented - foreach ($category->categoryAttributes as $attribute) { // if send only own attributes -> ancestors attributes will be detached
            foreach ($ancestorsAndSelfAttributes as $attribute) {
                // Check if the current attribute belongs to provided category
                if ($categoryAttributes->contains($attribute)) {
                    foreach ($allActions as $action) {
                        // Detach the action from the attribute
                        ($attribute->actions()->detach($action));
                    }
                } else {
                    foreach ($categoryActions as $action) {
                        // Detach the action from the attribute
                        ($attribute->actions()->detach($action));
                    }
                }

                // Loop through settings (from request) for each attribute
                foreach ($attributesIdsFromRequest as $attributeId => $requestActions) {
                    // If the category attribute ID matches the setting's attribute ID from request
                    if ($attribute->id === $attributeId) {
                        // Prepare an array to hold action settings
                        /**
                         * @var array $items Array to store action settings for the attribute.
                         */
                        $items = [];

                        // Loop through the actions for the attribute (actions from request)
                        foreach ($requestActions as $actionId => $settings) {
                            // Prepare an array to hold each setting data
                            /**
                             * @var array $setting Array to hold each setting data for an action.
                             */
                            $setting = [];
                            $setting['required'] = false;
                            $setting['column'] = false;
                            $setting['excluded'] = false;

                            // Loop through settings from request to determine flags
                            foreach ($settings as $key => $value) {
                                if ($key === 'required') {
                                    $setting['required'] = true; // Mark as required if the flag is set
                                }
                                if ($key === 'column') {
                                    $setting['column'] = true; // Mark as a column if the flag is set
                                }
                                if ($key === 'excluded') {
                                    $setting['excluded'] = true; // Mark as excluded if the flag is set
                                }
                            }
                            // Store the setting for the current action ID
                            $items[$actionId] = $setting;
                        }
                        // Attach the actions to the attribute with the corresponding settings stored in the pivot table.
                        $attribute->actions()->attach($items);
                    }
                }
            }
        });

        // For debug: return a response instead of redirecting to see Query Count in DebugBar
        // return view('test');
        // Redirect back with a success message
        return redirect()->back()->with('success', 'Settings were saved successfully!');
    }
}
