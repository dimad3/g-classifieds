<?php

declare(strict_types=1);

namespace App\Models\Adverts;

use App\Models\Action\Action;
use App\Models\Action\ActionAttributeSetting;
use App\Models\Adverts\Advert\Advert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalnoy\Nestedset\NodeTrait;

class Category extends Model
{
    use HasFactory;
    use NodeTrait;  // Add this trait for nested set functionality

    /**
     * If you do not wish to have timestamps columns automatically maintained, set the property to false
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'advert_categories';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'slug', 'sort', 'parent_id'];

    // private ?Collection $cachedAncestors = null;
    private ?EloquentCollection $cachedAncestorsAndMe = null;

    private ?EloquentCollection $cachedAllAttributes = null;

    private ?EloquentCollection $cachedExcludedAttributesForAncestors = null;

    private ?EloquentCollection $cachedExcludedAttributesForSelfAndAncestors = null;

    // Relationships === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * @comment Get the adverts for the category
     */
    public function adverts(): HasMany
    {
        return $this->hasMany(Advert::class);
    }

    /**
     * @comment Get the attributes for the category
     * the name is categoryAttributes to avoid conflicts because Models has 'attributes' property
     */
    public function categoryAttributes(): HasMany
    {
        return $this->hasMany(Attribute::class)->orderBy('sort');
    }

    /**
     * @comment Get ancestors' attributes from `advert_category_inherited_attributes_exclusions` table
     * which must be excluded for this category and its descendants
     * (can not be assigned to this category and its descendants).
     */
    public function inheritedAttributesExcluded(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'advert_category_inherited_attributes_exclusions');
    }

    /**
     * @comment The actions that belong to the category (many-to-many), (including excluded actions).
     */
    public function allActions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class)
            ->withPivot(['sort', 'excluded'])
            ->orderByPivot('sort');
    }

    /**
     * @comment The actions that belong to the category (many-to-many).
     */
    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class)
            ->withPivot(['sort', 'excluded'])
            ->wherePivot('excluded', false)
            // ->orderBy('sort');
            ->orderByPivot('sort');
    }

    /**
     * @comment Get ancestors' actions from `action_category` table which must be excluded for this category
     * and its descendants (can not be assigned to this category and its descendants).
     */
    public function actionsExcluded(): BelongsToMany
    {
        return $this->belongsToMany(Action::class)
            ->withPivot(['sort', 'excluded'])
            ->wherePivot('excluded', true)
            ->orderByPivot('sort');
    }

    /**
     * Get all of the settings for the category.
     */
    public function settings(): HasManyThrough
    {
        return $this->hasManyThrough(ActionAttributeSetting::class, Attribute::class);
    }

    /**
     * Get all of the settings for the category.
     */
    public function settingsWithActions(): HasManyThrough
    {
        return $this->settings()->whereNotNull('action_id');
    }

    /**
     * Get all of the settings for the category.
     */
    public function settingsWithoutActions(): HasManyThrough
    {
        return $this->settings()->where('action_id', null);
    }

    // Actions Methods === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * Get all ancestors actions
     */
    // public function ancestorsActions(): Collection
    // {
    //     return $this->parent ? $this->parent->ancestorsAndMyActions() : new EloquentCollection;
    // }

    /**
     * Get only parent actions
     */
    // public function parentActions(): Collection
    // {
    //     return $this->parent ? $this->parent->actions()->orderBy('name') : new EloquentCollection;
    // }

    /**
     * Get all ancestors actions and own actions
     */
    // public function ancestorsAndMyActions(): Collection
    // {
    //     $categoryAndItsAncestors = $this->ancestorsAndSelf($this->id)->loadMissing([
    //         'actions' => function ($query): void {
    //             $query->orderBy('name');
    //         },
    //     ]);

    //     $ancestorsAndMyActions = new EloquentCollection;
    //     foreach ($categoryAndItsAncestors as $category) {
    //         $ancestorsAndMyActions = $ancestorsAndMyActions->merge($category->actions);
    //     }

    //     return $ancestorsAndMyActions->sortBy('slug');
    // }

    /**
     * Get all descendants actions
     */
    // public function descendantsActions(): Collection
    // {
    //     ($descendants = $this->descendants->loadMissing(['actions']));

    //     $descendantsActions = new EloquentCollection;
    //     foreach ($descendants as $category) {
    //         $descendantsActions = $descendantsActions->concat($category->actions);
    //     }

    //     return $descendantsActions;
    // }

    /**
     * Get only ASSIGNED actions for collection of categories or a single category
     */
    public function getAssignedActions(EloquentCollection|Category $categories): Collection
    {
        // dump($categories); // no `ancestors` relations
        // $result = $this->getActions($categories)['assignedActions'];
        // dd($categories);    // `allActions` relations are set. Why?
        return $this->getActions($categories)['assignedActions'];
    }

    /**
     * Get only ASSIGNED actions for collection of categories or a single category
     */
    public function getExcludedActions(EloquentCollection|Category $categories): Collection
    {
        return $this->getActions($categories)['excludedActions'];
    }

    /**
     * Get only ASSIGNED actions for collection of categories or a single category
     */
    public function getAdjustedActions(EloquentCollection|Category $categories): Collection
    {
        return $this->getActions($categories)['adjustedActions'];
    }

    /**
     * Get only actions which are assigned to $this category
     * and order these actions by their sort values from action_category table
     * commented on 23.06.2024 because getActions() methods can be used instead
     */
    // public function getMyOrderedActions(): Collection
    // {
    //     ($actions = DB::table('actions')
    //         ->join('action_category', 'actions.id', '=', 'action_category.action_id')
    //         ->select('actions.*', 'action_category.sort', 'action_category.excluded')
    //         ->where('category_id', $this->id)
    //         ->where('excluded', false)
    //         ->orderBy('action_category.sort')
    //         ->orderBy('actions.name')
    //         ->get());
    //     // return 1;
    //     return $actions;
    // }

    /**
     * Get actions which are assigned to collection of categories
     * and order these actions by their sort values from action_category table.
     * commented on 23.06.2024 because getActions() methods can be used instead
     */
    // public function getOrderedActions(Collection|null $categoriesIds = null): Collection
    // public function getOrderedActions(Collection $categoriesIds): Collection
    // {
    //     ($orderedActions = DB::table('actions')
    //         ->join('action_category', 'actions.id', '=', 'action_category.action_id')
    //         ->select('actions.*', 'action_category.sort', 'excluded')
    //         ->where('excluded', false)
    //         ->whereIn('category_id', $categoriesIds)
    //         ->orderBy('action_category.sort')
    //         ->orderBy('actions.name')
    //         ->get());

    //         return $orderedActions;
    // }

    /**
     * Get all actions from db `actions`table along to $this category assigned actions
     * and order these actions by their sort values from action_category table
     */
    public function getAllActions(): Collection
    {
        $actions = DB::table('actions')
            ->leftJoin('action_category', function (JoinClause $join): void {
                $join->on('actions.id', '=', 'action_id')
                    ->where('category_id', $this->id);
            })
            // ->select('id', 'name', 'sort')
            ->select(DB::raw('actions.id, name, IF(sort is null,0,1) as is_assigned, COALESCE(sort, 200) as sort_with_default'))
            ->orderByDesc('is_assigned')
            ->orderBy('sort_with_default')
            ->orderBy('name')
            ->get();

        return $actions;
    }

    // methods to check actions existance === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    public function ancestorsOrDescendantsOrMeHaveActions(): bool
    {
        ($ancestorsAndMe = $this->ancestorsAndMe());
        // ($descendants = $this->descendantsOf($this->id));
        ($descendants = $this->descendants);
        $categories = $ancestorsAndMe->concat($descendants);
        $categories = $categories->loadMissing('actions');
        foreach ($categories as $category) {
            if ($category->actions->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    public function ancestorsHaveActions(): bool
    {
        $ancestors = $this->ancestors->loadMissing('actions');
        foreach ($ancestors as $category) {
            if ($category->actions->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    public function descendantsOrMeHaveActions(): bool
    {
        ($descendantsAndMe = $this->descendants->prepend($this));
        ($descendantsAndMe = $descendantsAndMe->loadMissing('actions'));
        foreach ($descendantsAndMe as $category) {
            if ($category->actions->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    public function allAncestorsActionsAreExcluded(): bool
    {
        // ($actions = $this->getAdjustedActions($this->ancestors));
        ($actions = $this->getAdjustedActions($this->ancestorsAndMe()));
        if ($actions->isEmpty()) {
            return true;
        }

        return false;
    }

    // action_category_settings methods === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * Get all ancestors settings and own settings
     */
    public function ancestorsAndMySettings(): Collection
    {
        $categoryAndItsAncestors = $this->ancestorsAndMe()->loadMissing(['settings']);

        $ancestorsAndMySettings = new EloquentCollection;
        foreach ($categoryAndItsAncestors as $category) {
            $ancestorsAndMySettings = $ancestorsAndMySettings->merge($category->settings);
        }

        return $ancestorsAndMySettings;
    }

    /**
     * Get all descendants settings
     */
    public function descendantsSettings(): Collection
    {
        $descendants = $this->descendants->loadMissing(['settings']);

        $descendantsSettings = new EloquentCollection;
        foreach ($descendants as $category) {
            $descendantsSettings = $descendantsSettings->merge($category->settings);
        }

        return $descendantsSettings;
    }

    public function hasSettingsForAttributesWithoutActions(): bool
    {
        return $this->settingsWithoutActions()->exists();
    }

    public function ancestorsOrMyAttributesHaveSettingsWithoutActions(): bool
    {
        ($ancestorsAndMe = $this->ancestorsAndMe()->loadMissing('settingsWithoutActions'));
        foreach ($ancestorsAndMe as $category) {
            if ($category->settingsWithoutActions->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    // Other Methods === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    public function ancestorsAndMe(): EloquentCollection
    {
        return $this->cachedAncestorsAndMe ??= new EloquentCollection(
            $this->ancestors()->orderBy('_lft')->get()->push(clone $this)
        );
    }

    public function hasParent(): bool
    {
        return (bool) $this->parent_id;
    }

    /**
     * Get category's path (slugs of ancestors' + category's slug divided by "/")
     */
    public function getPath(): string
    {
        // Lesson 8 - 01:41:00 - explanation
        // bugs are possible (see Bug Nr.1 and https://chatgpt.com/c/670b8ba6-e800-800f-b367-9228dbc34ede) -> commented on 13.10.2024
        // return implode('/', array_merge($this->ancestors()->pluck('slug')->toArray(), [$this->slug]));

        $ancestorsAndSelf = $this->ancestorsAndSelf($this->id)->sortBy('_lft');

        return implode('/', $ancestorsAndSelf->pluck('slug')->toArray());
    }

    /**
     * Create a slug.
     * https://laracasts.com/discuss/channels/general-discussion/l5-routing-1
     *
     * @param  string  $name  string to be transformed in slug
     * @param  int  $parentId  parent category for which slug will be unique
     */
    public function makeUniqueSlugForParent(string $name, ?int $parentId): string
    {
        $slug = Str::slug($name);

        $count = $this->where('parent_id', $parentId)
            ->whereKeyNot($this->id)
            ->whereRaw("slug RLIKE '^{$slug}(-[0-9]+)?$'")->count();

        return $count ? "{$slug}-{$count}" : $slug;
    }

    /**
     * Get array of actions grouped by `excluded` for collection of categories (array['assignedActions', 'excludedActions', 'adjustedActions'])
     */
    // private function getActions(EloquentCollection $categories): array
    private function getActions(EloquentCollection|Category $categories): array
    {
        ($categories);
        ($categoriesClone = clone $categories);

        // ($categories = $categories->loadMissing(['allActions']));
        // if argument is single category -> convert one model instance to eloquent collection,
        // because we need collection for next steps
        if ($categories instanceof Category) {
            $collection = new EloquentCollection();
            $collection->push($categories);
            $categories = $collection;
        }
        // dd($categories); // no `allActions` relations
        ($categories->loadMissing(['allActions']));
        // ($categories->load(['allActions']));
        // dump($categories); // `allActions` relations are set. Why?

        $actions = [];
        foreach ($categories as $category) {
            foreach ($category->allActions as $action) {
                array_push($actions, $action->getOriginal());
            }
        }
        // reassign parameter, because it has `allActions` relations. If do not implement it ->
        // modified $categories will be passed to other methods by refrence, so bugs are possible. Am I right?
        ($categories = $categoriesClone);

        ($assignedActions = Action::hydrate(Arr::where($actions, function ($value, $key) {
            return $value['pivot_excluded'] === 0;
        }))->sortBy([
            ['pivot_sort', 'asc'],
            ['slug', 'asc'],
        ]));
        ($excludedActions = Action::hydrate(Arr::where($actions, function ($value, $key) {
            return $value['pivot_excluded'] === 1;
        }))->sortBy([
            ['pivot_sort', 'asc'],
            ['slug', 'asc'],
        ]));
        ($adjustedActions = $assignedActions->whereNotIn('id', $excludedActions?->pluck('id')));

        $result = [];
        $result['assignedActions'] = $assignedActions;
        $result['excludedActions'] = $excludedActions;
        $result['adjustedActions'] = $adjustedActions;
        // dd($categories); // no `allActions` relations

        return $result;
    }

    // Scopes === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * @comment Scope a query to only include categories which have all settings assigned
     */
    public function scopeWithAllSettingsAssigned(Builder $query): void
    {
        $query->whereIn('id', [1, 2, 3, 6, 12]);
    }
}
