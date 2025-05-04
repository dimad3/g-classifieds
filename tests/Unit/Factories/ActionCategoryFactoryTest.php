<?php

declare(strict_types=1);

namespace Tests\Unit\Factories;

use App\Models\Action\Action;
use App\Models\Action\ActionCategory;
use App\Models\Adverts\Category;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionCategoryFactoryTest extends TestCase
{
    // use RefreshDatabase;
    use DatabaseTransactions; // Use database transactions instead of RefreshDatabase

    /**
     * Test the ActionCategory factory creates valid entries.
     */
    public function test_action_category_factory_creates_valid_entries(): void
    {
        // Create an action and a category
        $action = Action::factory()->create();
        $category = Category::factory()->create();

        // Use the factory to create an ActionCategory
        $actionCategory = ActionCategory::factory()->create([
            'action_id' => $action->id,
            'category_id' => $category->id,
        ]);

        // Assert that the ActionCategory was created
        $this->assertDatabaseHas('action_category', [
            'id' => $actionCategory->id,
            'action_id' => $action->id,
            'category_id' => $category->id,
        ]);

        // Assert the relationships are valid
        $this->assertEquals($action->id, $actionCategory->action_id);
        $this->assertEquals($category->id, $actionCategory->category_id);
    }

    /**
     * Test ActionCategory factory does not create invalid entries.
     */
    public function test_action_category_factory_rejects_invalid_entries(): void
    {
        // Expect an exception if we try to create an ActionCategory with invalid IDs
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Attempt to create an ActionCategory with non-existent action and category IDs
        ActionCategory::factory()->create([
            'action_id' => 999, // Non-existent action_id
            'category_id' => 999, // Non-existent category_id
        ]);
    }

    /**
     * Test relationships between ActionCategory, Action, and Category.
     */
    public function test_action_category_relationships_are_correct(): void
    {
        // Create related entities
        $action = Action::factory()->create();
        $category = Category::factory()->create();

        // Create ActionCategory
        $actionCategory = ActionCategory::factory()->create([
            'action_id' => $action->id,
            'category_id' => $category->id,
        ]);

        // Assert the relationships are correct
        $this->assertInstanceOf(Action::class, $actionCategory->action);
        $this->assertInstanceOf(Category::class, $actionCategory->category);

        // Assert data matches
        $this->assertEquals($action->id, $actionCategory->action->id);
        $this->assertEquals($category->id, $actionCategory->category->id);
    }
}
