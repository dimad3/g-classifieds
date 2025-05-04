<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Adverts;

use App\Models\Action\Action;
use App\Models\Adverts\Category;
use App\Services\Adverts\CategoryAttributeService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;
use Tests\TestCase;

class CategoryAttributeServiceMockTest extends TestCase
{
    protected MockObject $serviceMock;

    protected MockObject $categoryMock;

    protected MockObject $actionMock;

    protected function setUp(): void
    {
        parent::setUp(); // Setup the test environment

        // Create the mock for CategoryAttributeService
        $this->serviceMock = $this->createMock(CategoryAttributeService::class);

        // Create the mocks for Category and Action
        $this->categoryMock = $this->createMock(Category::class);
        $this->actionMock = $this->createMock(Action::class);
    }

    public function test_retrieves_ancestors_and_self_attributes(): void
    {
        $ancestorsAndSelfCategories = new EloquentCollection([
            $this->createMock(Category::class),
            $this->createMock(Category::class),
            $this->createMock(Category::class),
            $this->createMock(Category::class),
            $this->createMock(Category::class),
            // Add as many mock categories as needed to simulate the ancestor hierarchy
        ]);
        $this->categoryMock->method('ancestorsAndMe')->willReturn($ancestorsAndSelfCategories);

        $this->categoryMock->method('loadMissing')->willReturnSelf();

        $attributes = $this->serviceMock->getAncestorsAndSelfAttributes($this->categoryMock);

        $this->assertInstanceOf(EloquentCollection::class, $attributes, 'Should return a collection of attributes');
        // Add more assertions here based on expected outcomes
    }

    /**
     * Test the retrieval of ancestors attributes from a category.
     */
    public function test_retrieves_ancestors_attributes(): void
    {
        // Create a mock collection of categories to simulate the ancestors
        $ancestorsCategories = new EloquentCollection([
            $this->createMock(Category::class),
            $this->createMock(Category::class),
            $this->createMock(Category::class),
            // Add as many mock categories as needed to simulate the ancestor hierarchy
        ]);

        // Set up the category mock to return the mock collection when ancestors() is called
        // willReturn($ancestorsCategories): defines what the ancestors() method should return when it's called during the test.
        $this->categoryMock->method('ancestors')->willReturn($ancestorsCategories);

        // Set up the category mock to return itself when loadMissing() is called.
        // willReturnSelf():Instructs the mock to return the same object instance when loadMissing() is called.
        // returnSelf(): means the method returns the original object it was called on.
        // This mimics the typical behavior of Laravel's loadMissing() method, which returns the model instance after loading missing relationships
        $this->categoryMock->method('loadMissing')->willReturnSelf();

        // Call the service method to get the ancestor attributes
        $attributes = $this->serviceMock->getAncestorsAttributes($this->categoryMock);

        // Assert that the returned attributes are an instance of EloquentCollection
        $this->assertInstanceOf(EloquentCollection::class, $attributes, 'Should return a collection of attributes from ancestors');

        // Further assertions based on expected behavior can be added here
    }

    // todo
    // public function test_retrieves_parent_attributes()
    // {
    //     $parentCategoryMock = $this->createMock(Category::class);
    //     $parentAttributes = new EloquentCollection([
    //         $this->createMock(Category::class),
    //         $this->createMock(Category::class),
    //         $this->createMock(Category::class),
    //         // Add as many mock categories as needed to simulate the ancestor hierarchy
    //     ]);

    //     // Mock the behavior of the parent's `categoryAttributes` property
    //     $parentCategoryMock->expects($this->once())
    //         ->method('getAttribute')
    //         ->with('categoryAttributes')
    //         ->willReturn($parentAttributes);

    //     // Mock the behavior of the `parent` property
    //     $this->categoryMock->expects($this->once())
    //         ->method('getAttribute')
    //         ->with('parent')
    //         ->willReturn($parentCategoryMock);

    //     // Test the service method
    //     $attributes = $this->serviceMock->getParentAttributes($this->categoryMock);

    //     // Assert that the returned attributes match the parent's attributes
    //     $this->assertInstanceOf(EloquentCollection::class, $attributes, "Should return an EloquentCollection");
    //     // $this->assertEquals($parentAttributes, $attributes, "Should return the parent's attributes");
    // }

    public function test_returns_empty_collection_if_no_parent(): void
    {
        $this->categoryMock->parent = null;

        $attributes = $this->serviceMock->getParentAttributes($this->categoryMock);

        $this->assertInstanceOf(EloquentCollection::class, $attributes, 'Should return an empty collection if no parent');
        $this->assertCount(0, $attributes, 'Should contain no attributes when parent is null');
    }

    public function test_retrieves_excluded_ancestors_attributes(): void
    {
        $categories = new EloquentCollection([/* mock categories */]);
        $this->categoryMock->method('ancestors')->willReturn($categories);

        $this->categoryMock->method('loadMissing')->willReturnSelf();

        $excludedAttributes = $this->serviceMock->getAncestorsAttributesExcluded($this->categoryMock);

        $this->assertInstanceOf(EloquentCollection::class, $excludedAttributes, 'Should return excluded attributes for ancestors');
    }

    public function test_filters_available_attributes_correctly(): void
    {
        $ancestorAttributes = new EloquentCollection([/* mock ancestor attributes */]);
        $excludedAttributes = new EloquentCollection([/* mock excluded attributes */]);

        $this->categoryMock->method('ancestors')->willReturn(new EloquentCollection());
        $this->serviceMock->method('getAncestorsAttributes')->willReturn($ancestorAttributes);
        $this->serviceMock->method('getAncestorsAttributesExcluded')->willReturn($excludedAttributes);

        $availableAttributes = $this->serviceMock->getAvailableAncestorsAttributes($this->categoryMock);

        $this->assertInstanceOf(EloquentCollection::class, $availableAttributes, 'Should return a collection of available attributes');
        // Add assertions to validate the filtering logic
    }

    public function test_considers_action_specific_exclusions(): void
    {
        $allAttributes = new EloquentCollection([/* mock attributes */]);
        $excludedForAction = new EloquentCollection([/* mock excluded attributes */]);

        $this->serviceMock->method('getAvailableAncestorsAndSelfAttributes')->willReturn($allAttributes);

        // $this->serviceMock->method('filterByAttributeSetting')->willReturn($excludedForAction);
        // Use reflection to call private method
        $method = new ReflectionMethod(CategoryAttributeService::class, 'filterByAttributeSetting');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->serviceMock, [$allAttributes, 'isExcluded', $this->actionMock]);

        $availableAttributes = $this->serviceMock->getAllAvailableAttributes($this->categoryMock, $this->actionMock);

        $this->assertInstanceOf(EloquentCollection::class, $availableAttributes, 'Should return available attributes considering action-specific exclusions');
        // Further validation as necessary
    }
}
