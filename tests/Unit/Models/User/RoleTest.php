<?php

declare(strict_types=1);

namespace Tests\Unit\Models\User;

use App\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Unit test for the User model's role-related methods.
 *
 * This test class validates the functionality of role-changing methods in the
 * `User` model, ensuring roles can be changed and appropriate exceptions are
 * thrown when trying to change to an already existing role.
 */
class RoleTest extends TestCase
{
    // Use the DatabaseTransactions trait to ensure that each test is run in a
    // transaction and is rolled back after completion, keeping the database state clean.
    use DatabaseTransactions;

    /**
     * Test changing a user's role.
     *
     * This test checks that a user's role can be successfully changed. Initially,
     * the user is created with the 'user' role, and after changing it to 'admin',
     * the user's role is confirmed to be updated.
     */
    public function testCanChangeRole(): void
    {
        // Create a user with the default role 'user'
        $user = User::factory(User::class)->create(['role' => User::ROLE_USER]);

        // Assert that the user is not an admin initially
        self::assertFalse($user->isAdmin());

        // Change the user's role to 'admin'
        $user->changeRole(User::ROLE_ADMIN);

        // Assert that the user is now an admin after role change
        self::assertTrue($user->isAdmin());
    }

    /**
     * Test changing a user's role to the same role they already have.
     *
     * This test checks that when a user tries to assign themselves the same role
     * that they already have, an exception is thrown with the correct message.
     */
    public function testThrowsExceptionWhenChangingToSameRole(): void
    {
        // Create a user already assigned the 'admin' role
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

        // Expect an exception to be thrown with a specific message when trying
        // to assign the same 'admin' role again to the user.
        $this->expectExceptionMessage("Role '{$user->role}' is already assigned for user '{$user->name}'.");

        // Attempt to change the user's role to 'admin', which should trigger an exception
        $user->changeRole(User::ROLE_ADMIN);
    }
}
