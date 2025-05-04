<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Action\Action;
use App\Models\Action\ActionAttributeSetting;
use App\Models\Adverts\Attribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Str;

class ActionController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:manage-actions');
    }

    public function index()
    {
        $actions = Action::query()
            ->orderBy('name')
            ->paginate(20);

        return view('admin.actions.index', compact('actions'));
    }

    public function create()
    {
        return $this->edit(new Action());
    }

    public function store(Request $request)
    {
        return $this->update($request, new Action());
    }

    public function edit(Action $action)
    {
        return view('admin.actions.create_or_edit', compact('action'));
    }

    /**
     * Validate and save (store or update) an action.
     */
    public function update(Request $request, Action $action)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                Rule::unique('actions')
                    ->ignore($request->action),   // Forcing A Unique Rule To Ignore A Given ID
                'string', 'min:2', 'max:16',
            ],
            'slug' => [
                Rule::requiredIf(function () use ($request) {
                    return $request->method() === 'PUT';
                }),
                Rule::unique('actions')
                    ->ignore($request->action),
                // Rule::unique('actions'),
                'string', 'min:2', 'max:16',
            ],
        ]);
        // dd($validated);
        $action->name = $request->name;
        if ($request->method() === 'POST') {
            // todo: generate unique slug
            $action->slug = Str::slug($request->name, '-');
        } else {
            $action->slug = $request->slug;
        }
        $result = $action->save();

        return redirect()->route('admin.actions.index');
    }

    public function destroy(Action $action)
    {
        if ($action->adverts()->exists()) {
            return redirect()
                ->back()
                ->with(
                    'error',
                    "You can not delete the action which has assigned to adverts!<br>
                    At first delete all adverts which have this action: [{$action->name}].<br>
                    Then try again."
                );
        }

        $action->delete();

        return redirect()->route('admin.actions.index')
            ->with('success', "Action: [{$action->name}] was deleted!");
    }

    /**
     * Store all actions' settings for attribute
     */
    public function storeSettings(Request $request, Attribute $attribute)
    {
        // dd($request->input());
        ($category = $attribute->category);
        ($actions = $category->actions);
        $validatedData = $request->validate([
            // https://laravel.com/docs/10.x/validation#rule-array
            // https://laravel.com/docs/10.x/validation#validating-nested-array-input
            // "actionsRequired"   => ['array'],
            'actionsRequired' => ['array:required'],
            'actionsRequired.*' => ['required', 'numeric', 'integer', 'distinct', Rule::in($actions->modelKeys())],

            // "actionsRequired"   => ['array:actionsColumn'],
            'actionsColumn' => ['array:actionsColumn'],
            'actionsColumn.*' => ['required', 'numeric', 'integer', 'distinct', Rule::in($actions->modelKeys())],

            // "actionsRequired"   => ['array:actionsExcluded'],
            'actionsExcluded' => ['array:actionsExcluded'],
            'actionsExcluded.*' => ['required', 'numeric', 'integer', 'distinct', Rule::in($actions->modelKeys())],
        ]);
        // dd((empty($validatedData)));
        // dd(ActionAttributeSetting::whereIn('attribute_id', $allAttributes->modelKeys())->get());
        ($requiredIds = data_get($validatedData, 'actionsRequired', []));
        ($columnIds = data_get($validatedData, 'actionsColumn', []));
        ($excludedIds = data_get($validatedData, 'actionsExcluded', []));
        ($allSettingsIds = array_unique(array_merge($requiredIds, $columnIds, $excludedIds)));
        // return 1;

        DB::transaction(function () use ($attribute, $actions, $allSettingsIds, $requiredIds, $columnIds, $excludedIds): void {
            $attribute->settings()->delete();
            // todo: run new settings inserts only if all records were deleted succesfuly
            foreach ($actions as $action) {
                // save setting only for action which is in allSettingsIds array
                if (in_array($action->id, $allSettingsIds)) {
                    $attributeSetting = new ActionAttributeSetting();
                    $attributeSetting->action_id = $action->id;
                    $attributeSetting->attribute_id = $attribute->id;
                    $attributeSetting->required = false;
                    $attributeSetting->column = false;
                    $attributeSetting->excluded = false;
                    if (in_array($action->id, $requiredIds)) {
                        $attributeSetting->required = true;
                    }

                    if (in_array($action->id, $columnIds)) {
                        $attributeSetting->column = true;
                    }

                    if (in_array($action->id, $excludedIds)) {
                        $attributeSetting->excluded = true;
                    }

                    // dump($attributeSetting);
                    $attribute->setting()->save($attributeSetting);
                }
            }
        });

        return redirect()->route('admin.adverts.categories.attributes.show', [$category, $attribute]);
    }
}
