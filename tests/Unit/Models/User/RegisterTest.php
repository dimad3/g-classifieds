<?php

declare(strict_types=1);

namespace Tests\Unit\Models\User;

use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * Test cases for user registration and verification flow.
 */
class RegisterTest extends TestCase
{
    // Use DatabaseTransactions to ensure each test runs within a transaction
    // that is rolled back after the test, preventing test data from persisting.
    use DatabaseTransactions;
    use WithFaker;

    /**
     * This test registers a new user and checks if the user is created
     * correctly with the expected attributes and the correct initial status.
     */
    public function testRegisterUser(): void
    {
        // Act: Register a new user with given name, email, and password
        $user = User::register(
            $name = $this->faker->firstname,
            $email = $this->faker->unique()->safeEmail,
            $password = bcrypt('secret'),
        );

        // Assert: Check if the user object is created and populated correctly
        self::assertNotEmpty($user);  // Ensure user is created

        // Assert: Check if user attributes are set correctly
        self::assertEquals($name, $user->name);  // Name is as expected
        self::assertEquals($email, $user->email);  // Email is as expected
        self::assertNotEmpty($user->password);  // Password should not be empty
        self::assertNotEquals($password, $user->password);  // Ensure password is not plain text

        // Assert: Check the user's initial status
        self::assertTrue($user->isWaiting());  // User should be in 'wait' status initially
        self::assertFalse($user->isActive());  // User should not be 'active' initially
        self::assertFalse($user->isAdmin());  // User should not be an 'admin' initially
    }

    /**
     * This test simulates the verification of a user after registration,
     * checking if the user's status transitions correctly to active.
     */
    public function testVerifyUserEmail(): void
    {
        // Act: Register a new user with given name, email, and password
        $user = User::register(
            $this->faker->firstname,
            $this->faker->unique()->safeEmail,
            bcrypt('secret'),
        );

        // Act: Verify user's email
        $user->verifyEmail();

        // Assert: Ensure the user's status is updated to active and 'email_verified_at' is set
        self::assertFalse($user->isWaiting());  // User should no longer be in 'waiting' status
        self::assertTrue($user->isActive());  // User should be 'active' after verification
        self::assertNotNull($user->email_verified_at);  // Ensure it's not null
        self::assertInstanceOf(Carbon::class, $user->email_verified_at);  // Ensure it's a valid Carbon date object
        self::assertTrue($user->email_verified_at->lte(now()));  // Check if email_verified_at is before or equal to the current time
    }

    /**
     * Test attempting to verify a user who is already verified.
     *
     * This test checks that when a user is already verified, attempting to verify
     * them again throws the appropriate exception with a relevant error message.
     */
    public function testTryToVerifyAlreadyVerifiedEmail(): void
    {
        // Arrange: Register a new user and verify them
        $user = User::register('name', 'email', 'password');
        $user->verifyEmail();

        // Assert: Try to verify the user again and expect an exception
        $this->expectExceptionMessage('User\'s email is already verified.');
        $user->verifyEmail();  // Trying to verify email again should throw an exception
    }
}
