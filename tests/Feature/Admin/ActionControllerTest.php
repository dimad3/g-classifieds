<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Action\Action;
use App\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionControllerTest extends TestCase
{
    // use RefreshDatabase;
    use DatabaseTransactions;

    public function test_index_displays_actions(): void
    {
        // Arrange: create a few actions
        $actions = Action::factory()->count(5)->create();

        // Act: make a GET request to the index route
        // $user - a User instance that implements Authenticatable,
        // so passing it to actingAs is correct and fully compatible.
        $response = $this->actingAs(User::factory()->admin()->createOne())->get(route('admin.actions.index'));

        // Assert: check if the actions appear on the page
        $response->assertStatus(200);
        foreach ($actions as $action) {
            // asserting that each action->name is visible within the returned HTML response.
            // If any action name is not present in the HTML, this assertion will fail
            $response->assertSee($action->name);
        }
    }

    public function test_index_displays_403_for_non_admin(): void
    {
        // Arrange: create a non-admin user
        $user = User::factory()->state(['role' => User::ROLE_USER])->create();

        // Act: make a GET request to the index route as a non-admin
        // $user - a User instance that implements Authenticatable,
        // so passing it to actingAs is correct and fully compatible.
        $response = $this->actingAs($user)->get(route('admin.actions.index'));

        // Assert: check if the response status is 403 Forbidden
        $response->assertStatus(403);
    }
}
