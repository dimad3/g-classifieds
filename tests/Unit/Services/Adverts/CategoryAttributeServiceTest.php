<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Adverts;

use App\Models\Action\Action;
use App\Models\Action\ActionAttributeSetting;
use App\Models\Adverts\Attribute;
use App\Models\Adverts\Category;
use App\Models\Adverts\CategoryInheritedAttributesExclusion;
use App\Services\Adverts\CategoryAttributeService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CategoryAttributeServiceTest extends TestCase
{
    // use RefreshDatabase;
    use DatabaseTransactions; // Use database transactions instead of RefreshDatabase

    private CategoryAttributeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CategoryAttributeService::class); // Inject the service to be tested
    }

    public function test_get_ancestors_and_self_attributes(): void
    {
        // Setup categories and their attributes
        $category = Category::factory()->create();
        $ancestor1 = Category::factory()->create();
        $ancestor2 = Category::factory()->create();
        $ancestor1->appendNode($category); // make $category a child of ancestor1
        $ancestor2->appendNode($ancestor1); // make $ancestor1 a child of ancestor2

        // dump($ancestor2->getAttributes());
        // dump($ancestor1->getAttributes());
        // dump($category->getAttributes());

        Attribute::factory()->count(2)->create(['category_id' => $ancestor2->id]);
        Attribute::factory()->count(3)->create(['category_id' => $ancestor1->id]);
        Attribute::factory()->count(4)->create(['category_id' => $category->id]);

        // Get attributes for the category and its ancestors
        $attributes = $this->service->getAncestorsAndSelfAttributes($category);

        // Assert the correct collection type and count
        $this->assertInstanceOf(EloquentCollection::class, $attributes);
        $this->assertCount(9, $attributes); // 2+3+4
    }

    public function test_get_ancestors_attributes(): void
    {
        // Setup categories and their attributes
        $category = Category::factory()->create();
        $ancestor1 = Category::factory()->create();
        $ancestor2 = Category::factory()->create();
        $ancestor1->appendNode($category); // make $category a child of ancestor1
        $ancestor2->appendNode($ancestor1); // make $ancestor1 a child of ancestor2

        Attribute::factory()->count(2)->create(['category_id' => $ancestor2->id]);
        Attribute::factory()->count(3)->create(['category_id' => $ancestor1->id]);
        Attribute::factory()->count(4)->create(['category_id' => $category->id]);

        // Get attributes for the ancestors only
        $attributes = $this->service->getAncestorsAttributes($category);

        // Assert the correct collection type and count
        $this->assertInstanceOf(EloquentCollection::class, $attributes);
        $this->assertCount(5, $attributes); // 2+3
    }

    public function test_get_parent_attributes(): void
    {
        // Setup categories and their attributes
        $category = Category::factory()->create();
        $ancestor1 = Category::factory()->create();
        $ancestor2 = Category::factory()->create();
        $ancestor1->appendNode($category); // make $category a child of ancestor1
        $ancestor2->appendNode($ancestor1); // make $ancestor1 a child of ancestor2

        // Attribute::factory()->count(2)->create(['category_id' => $ancestor2->id]);
        // Attribute::factory()->count(3)->create(['category_id' => $ancestor1->id]);
        // Attribute::factory()->count(4)->create(['category_id' => $category->id]);
        $ancestor2->categoryAttributes()
            ->saveMany(Attribute::factory()->count(2)->create());
        $ancestor1->categoryAttributes()
            ->saveMany(Attribute::factory()->count(3)->create());
        $category->categoryAttributes()
            ->saveMany(Attribute::factory()->count(4)->create());

        // Get attributes for the parent category
        $attributes = $this->service->getParentAttributes($category);

        // Assert the correct collection type and count
        $this->assertInstanceOf(EloquentCollection::class, $attributes);
        $this->assertCount(3, $attributes); // Only from parent
    }

    public function test_get_ancestors_attributes_excluded(): void
    {
        // Setup categories and attributes, including excluded ones
        $category = Category::factory()->create();
        $ancestor1 = Category::factory()->create();
        $ancestor2 = Category::factory()->create();
        $ancestor1->appendNode($category); // make $category a child of ancestor1
        $ancestor2->appendNode($ancestor1); // make $ancestor1 a child of ancestor2

        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $ancestor2->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(2)->create(['category_id' => $ancestor1->id]);

        // Get excluded attributes for the ancestors
        $attributes = $this->service->getAncestorsAttributesExcluded($category);

        // Assert the correct collection type and count
        $this->assertInstanceOf(EloquentCollection::class, $attributes);
        $this->assertCount(3, $attributes); // Excluded attributes from ancestor
    }

    public function test_get_ancestors_and_self_attributes_excluded(): void
    {
        // Setup categories and attributes, including excluded ones
        $category = Category::factory()->create();
        $ancestor1 = Category::factory()->create();
        $ancestor2 = Category::factory()->create();
        $ancestor1->appendNode($category); // make $category a child of ancestor1
        $ancestor2->appendNode($ancestor1); // make $ancestor1 a child of ancestor2

        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $ancestor2->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $ancestor1->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(2)->create(['category_id' => $category->id]);

        // Get excluded attributes for the ancestors
        $attributes = $this->service->getAncestorsAndSelfAttributesExcluded($category);

        // Assert the correct collection type and count
        $this->assertInstanceOf(EloquentCollection::class, $attributes);
        $this->assertCount(4, $attributes); // Excluded attributes from ancestor and this category
    }

    public function test_get_available_ancestors_attributes(): void
    {
        // Setup categories and attributes, including excluded ones
        $category = Category::factory()->create();
        $ancestor1 = Category::factory()->create();
        $ancestor2 = Category::factory()->create();
        $ancestor1->appendNode($category); // make $category a child of ancestor1
        $ancestor2->appendNode($ancestor1); // make $ancestor1 a child of ancestor2

        $attribute21 = Attribute::factory()->create(['category_id' => $ancestor2->id]);
        $attribute22 = Attribute::factory()->create(['category_id' => $ancestor2->id]);
        $attribute11 = Attribute::factory()->create(['category_id' => $ancestor1->id]);
        $attribute12 = Attribute::factory()->create(['category_id' => $ancestor1->id]);
        $attribute13 = Attribute::factory()->create(['category_id' => $ancestor1->id]);
        $attribute01 = Attribute::factory()->create(['category_id' => $category->id]);
        $attribute02 = Attribute::factory()->create(['category_id' => $category->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $ancestor2->id, 'attribute_id' => $attribute21->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $ancestor1->id, 'attribute_id' => $attribute11->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $ancestor1->id, 'attribute_id' => $attribute12->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $category->id, 'attribute_id' => $attribute01->id]);

        // Get non-excluded attributes for ancestors
        $attributes = $this->service->getAvailableAncestorsAttributes($category);

        // Assert the correct collection type and count
        $this->assertInstanceOf(EloquentCollection::class, $attributes);
        $this->assertCount(2, $attributes); // 5 total - (1+2 excluded) = 2
    }

    public function test_get_available_ancestors_and_self_attributes(): void
    {
        // Setup categories and attributes, including excluded ones
        $category = Category::factory()->create();
        $ancestor1 = Category::factory()->create();
        $ancestor2 = Category::factory()->create();
        $ancestor1->appendNode($category); // make $category a child of ancestor1
        $ancestor2->appendNode($ancestor1); // make $ancestor1 a child of ancestor2

        $attribute21 = Attribute::factory()->create(['category_id' => $ancestor2->id]);
        $attribute22 = Attribute::factory()->create(['category_id' => $ancestor2->id]);
        $attribute11 = Attribute::factory()->create(['category_id' => $ancestor1->id]);
        $attribute12 = Attribute::factory()->create(['category_id' => $ancestor1->id]);
        $attribute13 = Attribute::factory()->create(['category_id' => $ancestor1->id]);
        $attribute01 = Attribute::factory()->create(['category_id' => $category->id]);
        $attribute02 = Attribute::factory()->create(['category_id' => $category->id]);
        $attribute03 = Attribute::factory()->create(['category_id' => $category->id]);
        $attribute04 = Attribute::factory()->create(['category_id' => $category->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $ancestor2->id, 'attribute_id' => $attribute21->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $ancestor1->id, 'attribute_id' => $attribute11->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $ancestor1->id, 'attribute_id' => $attribute12->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $category->id, 'attribute_id' => $attribute01->id]);

        // Get non-excluded attributes for ancestors
        $attributes = $this->service->getAvailableAncestorsAndSelfAttributes($category);

        // Assert the correct collection type and count
        $this->assertInstanceOf(EloquentCollection::class, $attributes);
        $this->assertCount(5, $attributes); // 9 total - (1+2+1 excluded) = 5
    }

    public function test_get_all_available_attributes(): void
    {
        // Setup categories and attributes, and an action for filtering
        $action = Action::factory()->create();

        $category = Category::factory()->create();
        $ancestor1 = Category::factory()->create();
        $ancestor2 = Category::factory()->create();
        $ancestor1->appendNode($category); // make $category a child of ancestor1
        $ancestor2->appendNode($ancestor1); // make $ancestor1 a child of ancestor2

        $attribute21 = Attribute::factory()->create(['category_id' => $ancestor2->id]);
        $attribute22 = Attribute::factory()->create(['category_id' => $ancestor2->id]);
        $attribute11 = Attribute::factory()->create(['category_id' => $ancestor1->id]);
        $attribute12 = Attribute::factory()->create(['category_id' => $ancestor1->id]);
        $attribute13 = Attribute::factory()->create(['category_id' => $ancestor1->id]);
        $attribute01 = Attribute::factory()->create(['category_id' => $category->id]);
        $attribute02 = Attribute::factory()->create(['category_id' => $category->id]);
        $attribute03 = Attribute::factory()->create(['category_id' => $category->id]);
        $attribute04 = Attribute::factory()->create(['category_id' => $category->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $ancestor2->id, 'attribute_id' => $attribute21->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $ancestor1->id, 'attribute_id' => $attribute11->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $ancestor1->id, 'attribute_id' => $attribute12->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $category->id, 'attribute_id' => $attribute01->id]);

        ActionAttributeSetting::factory()
            ->count(1)->create([
                'action_id' => $action->id,
                'attribute_id' => $attribute02->id, // only category's attribute can be excluded
                'required' => false,
                'column' => false,
                'excluded' => true,
            ]);
        ActionAttributeSetting::factory()
            ->count(1)->create([
                'action_id' => $action->id,
                'attribute_id' => $attribute03->id, // only category's attribute can be excluded
                'required' => false,
                'column' => false,
                'excluded' => true,
            ]);

        // Get all available attributes for the category and its ancestors
        $attributes = $this->service->getAllAvailableAttributes($category, $action);

        // Assert the correct collection type and count
        $this->assertInstanceOf(EloquentCollection::class, $attributes);
        $this->assertCount(3, $attributes); // 9 total - (1+2+1+2 excluded) = 3
    }

    public function test_get_all_attributes_excluded(): void
    {
        // Setup categories and attributes, and an action for filtering
        $action = Action::factory()->create();

        $category = Category::factory()->create();
        $ancestor1 = Category::factory()->create();
        $ancestor2 = Category::factory()->create();
        $ancestor1->appendNode($category); // make $category a child of ancestor1
        $ancestor2->appendNode($ancestor1); // make $ancestor1 a child of ancestor2

        $attribute21 = Attribute::factory()->create(['category_id' => $ancestor2->id]);
        $attribute22 = Attribute::factory()->create(['category_id' => $ancestor2->id]);
        $attribute11 = Attribute::factory()->create(['category_id' => $ancestor1->id]);
        $attribute12 = Attribute::factory()->create(['category_id' => $ancestor1->id]);
        $attribute13 = Attribute::factory()->create(['category_id' => $ancestor1->id]);
        $attribute01 = Attribute::factory()->create(['category_id' => $category->id]);
        $attribute02 = Attribute::factory()->create(['category_id' => $category->id]);
        $attribute03 = Attribute::factory()->create(['category_id' => $category->id]);
        $attribute04 = Attribute::factory()->create(['category_id' => $category->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $ancestor2->id, 'attribute_id' => $attribute21->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $ancestor1->id, 'attribute_id' => $attribute11->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $ancestor1->id, 'attribute_id' => $attribute12->id]);
        CategoryInheritedAttributesExclusion::factory()
            ->count(1)->create(['category_id' => $category->id, 'attribute_id' => $attribute01->id]);

        ActionAttributeSetting::factory()
            ->count(1)->create([
                'action_id' => $action->id,
                'attribute_id' => $attribute02->id, // only category's attribute can be excluded
                'required' => false,
                'column' => false,
                'excluded' => true,
            ]);
        ActionAttributeSetting::factory()
            ->count(1)->create([
                'action_id' => $action->id,
                'attribute_id' => $attribute03->id, // only category's attribute can be excluded
                'required' => false,
                'column' => false,
                'excluded' => true,
            ]);

        // Get all excluded attributes for the category and action
        $excluded = $this->service->getAllAttributesExcluded($category, $action);

        // Assert the correct collection type and count
        $this->assertInstanceOf(EloquentCollection::class, $excluded);
        $this->assertCount(6, $excluded); // 9 total - 3 available = (1+2+1+2 excluded)
    }

    public function test_get_required_attributes(): void
    {
        $numRequired = 3;
        // Setup attributes and an action
        $action = Action::factory()->create();

        $attributes = Attribute::factory()->count(5)->create();

        foreach ($attributes->take($numRequired) as $key => $attribute) {
            ActionAttributeSetting::factory()
                ->count(1)->create([
                    'action_id' => $action->id,
                    'attribute_id' => $attribute->id,
                    'required' => true,
                    'column' => true,
                    'excluded' => false,
                ]);
        }

        // Get required attributes for the action
        $required = $this->service->getRequiredAttributes($attributes, $action);

        // Assert the correct collection type and count
        $this->assertInstanceOf(EloquentCollection::class, $required);
        $this->assertCount($numRequired, $required); // 3 required
    }

    public function test_get_column_attributes(): void
    {
        $numRequired = 2;
        // Setup attributes and an action
        $action = Action::factory()->create();

        $attributes = Attribute::factory()->count(5)->create();

        foreach ($attributes->take($numRequired) as $key => $attribute) {
            ActionAttributeSetting::factory()
                ->count(1)->create([
                    'action_id' => $action->id,
                    'attribute_id' => $attribute->id,
                    'required' => false,
                    'column' => true,
                    'excluded' => false,
                ]);
        }

        // Get required attributes for the action
        $columns = $this->service->getColumnAttributes($attributes, $action);

        // Assert the correct collection type and count
        $this->assertInstanceOf(EloquentCollection::class, $columns);
        $this->assertCount($numRequired, $columns); // 2 columns
    }

    public function test_get_excluded_attributes_for_action(): void
    {
        $numRequired = 4;
        // Setup attributes and an action
        $action = Action::factory()->create();

        $attributes = Attribute::factory()->count(10)->create();

        foreach ($attributes->take($numRequired) as $key => $attribute) {
            ActionAttributeSetting::factory()
                ->count(1)->create([
                    'action_id' => $action->id,
                    'attribute_id' => $attribute->id,
                    'required' => false,
                    'column' => false,
                    'excluded' => true,
                ]);
        }

        // Get required attributes for the action
        $excluded = $this->service->getExcludedAttributesForAction($attributes, $action);

        // Assert the correct collection type and count
        $this->assertInstanceOf(EloquentCollection::class, $excluded);
        $this->assertCount($numRequired, $excluded); // 4 excluded
    }
}
