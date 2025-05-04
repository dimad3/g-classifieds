<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Test registration and email verification flow.
 */
class RegisterTest extends TestCase
{
    // Use DatabaseTransactions to ensure each test runs within a transaction
    // that is rolled back after the test, preventing test data from persisting.
    use DatabaseTransactions;

    /**
     * Test if the registration form loads successfully.
     */
    public function testRegistrationFormLoads(): void
    {
        // Arrange: Make a GET request to the registration page
        $response = $this->get(route('register'));

        // Assert: Ensure the page loads with a 200 status and contains the 'Register', 'Email', 'Password', 'Confirm' texts
        $response
            ->assertStatus(200)
            ->assertSee(['Register', 'Name', 'Email', 'Password', 'Confirm Password']);
    }

    /**
     * Test validation errors for empty registration fields.
     */
    public function testValidationErrorsOnEmptyFields(): void
    {
        // Arrange: Submit an empty registration form (missing required fields)
        $response = $this->post(route('register', [
            'name' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
        ]));

        // Assert: Check for 302 redirect and required validation errors for 'name', 'email', and 'password'
        $response
            ->assertStatus(302)
            ->assertSessionHasErrors(['name', 'email', 'password']);
    }

    /**
     * Test successful registration and redirection to login page.
     */
    public function testSuccessfulRegistration(): void
    {
        // Arrange: Create a new user instance (not saved to the database)
        $user = User::factory()->make();

        // Act: Submit the registration form with valid data
        $response = $this->post(route('register', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'secret',
            'password_confirmation' => 'secret',
        ]));

        // Assert: Ensure successful registration (302 redirect to login page with success message)
        $response
            ->assertStatus(302)
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('success', 'Check your email and click on the link to verify.');
    }
}
