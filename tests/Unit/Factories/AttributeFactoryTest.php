<?php

declare(strict_types=1);

namespace Tests\Unit\Factories;

use App\Models\Adverts\Attribute;
use App\Models\Adverts\Category;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeFactoryTest extends TestCase
{
    // use RefreshDatabase;
    use DatabaseTransactions; // Use database transactions instead of RefreshDatabase

    public function test_attribute_factory_can_create_advert_attribute(): void
    {
        // Create an AdvertAttribute using the factory
        $advertAttribute = Attribute::factory()->create();

        // Assert that the attribute was created successfully
        $this->assertDatabaseHas('advert_attributes', [
            'id' => $advertAttribute->id,
            'category_id' => $advertAttribute->category_id,
            'name' => $advertAttribute->name,
            'sort' => 200, // Check if the default sort value is set correctly
            'type' => $advertAttribute->type,
            // todo:
            // 'options' => $advertAttribute->options, // Use the raw attribute here
            // 'options' => $this->normalizeJsonOptions($advertAttribute->options), // Convert to JSON string for comparison
        ]);

        // Validate type is one of the defined types
        $this->assertContains($advertAttribute->type, array_keys(Attribute::typesList()));

        // Check if options are set correctly based on the type
        if (in_array($advertAttribute->type, ['string', 'json'])) {
            $this->assertNotNull($advertAttribute->options);
        }
        // $this->assertEquals(json_encode([]), $advertAttribute->options);
        // $this->assertEquals([], $advertAttribute->options);

    }

    public function test_attribute_factory_can_create_advert_attributes_in_bulk(): void
    {
        $numAttributes = 50;

        // Create N AdvertAttributes using the factory
        $advertAttributes = Attribute::factory()->count($numAttributes)->create();

        // Assert that N records were created
        $this->assertCount($numAttributes, $advertAttributes);

        // Assert that the records exist in the database
        foreach ($advertAttributes as $advertAttribute) {
            $this->assertDatabaseHas('advert_attributes', [
                'id' => $advertAttribute->id,
                'category_id' => $advertAttribute->category_id,
                'name' => $advertAttribute->name,
                'sort' => 200, // Assuming the default sort value is 200
                'type' => $advertAttribute->type,
                // todo:
                // 'options' => $advertAttribute->options,
                // 'options' => $this->normalizeJsonOptions($advertAttribute->options), // Convert to JSON string for comparison
            ]);
        }

        // Check for specific types
        $typeValues = array_column($advertAttributes->toArray(), 'type');
        $this->assertContains('string', $typeValues); // Example: Check if at least one type is 'string'
        $this->assertContains('integer', $typeValues); // Example: Check if at least one type is 'integer'
        $this->assertContains('float', $typeValues); // Example: Check if at least one type is 'float'
        $this->assertContains('json', $typeValues); // Example: Check if at least one type is 'json'
        $this->assertContains('boolean', $typeValues); // Example: Check if at least one type is 'boolean'
        $this->assertNotContains('wrong-string', $typeValues); // Assert that 'wrong-string' is not in the array

        $validTypes = array_keys(Attribute::typesList()); // an array of valid types
        // Assert that all types in $typeValues are valid
        foreach ($typeValues as $typeValue) {
            $this->assertContains($typeValue, $validTypes, "The type '{$typeValue}' is not a valid type.");
        }
    }

    /**
     * Test relationships between Attribute and Category.
     */
    public function test_attribute_relationships_are_correct(): void
    {
        // Create related entities
        $category = Category::factory()->create();

        // Create Attribute
        $attribute = Attribute::factory()->create([
            'category_id' => $category->id,
        ]);

        // Assert the relationships are correct
        $this->assertInstanceOf(Category::class, $attribute->category);

        // Assert data matches
        $this->assertEquals($category->id, $attribute->category->id);
    }
}
