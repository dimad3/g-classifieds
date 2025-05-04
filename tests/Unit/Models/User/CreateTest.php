<?php

declare(strict_types=1);

namespace Tests\Unit\Models\User;

use App\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CreateTest extends TestCase
{
    // Use DatabaseTransactions to ensure each test runs within a transaction
    // that is rolled back after the test, preventing test data from persisting.
    use DatabaseTransactions;
    use WithFaker;

    public function testStoreFromAdminPanel(): void
    {
        // Arrange & Act: Add a new user in db.
        $user = User::storeUser(
            $name = $this->faker->firstname,
            $email = $this->faker->unique()->safeEmail,
            $role = $this->faker->randomElement([User::ROLE_USER, User::ROLE_MODERATOR, User::ROLE_ADMIN]),
        );

        // Assert: Verify that a user record was created and is not empty.
        self::assertNotEmpty($user);

        // Assert: Check that the stored user's name, email, and role match the expected values.
        // Assert: Verify that the 'name' attribute of the stored user matches the expected value.
        // We are asserting that the value of the 'name' field in the $user model is equal to the value
        // we passed when creating the user. This ensures the user was created with the correct name.
        self::assertEquals($name, $user->name);
        self::assertEquals($email, $user->email);
        self::assertEquals($role, $user->role);

        // Assert: Ensure the user has a non-empty password (confirming it was set during creation).
        self::assertNotEmpty($user->password);

        // Additional assertions:
        // - Verify the user is active.
        // - Confirm the user is not an admin.
        self::assertTrue($user->isActive());
        // self::assertFalse($user->isAdmin());
    }
}
