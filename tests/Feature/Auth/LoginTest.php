<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Log;
use Tests\TestCase;

class LoginTest extends TestCase
{
    // Use DatabaseTransactions to ensure each test runs within a transaction
    // that is rolled back after the test, preventing test data from persisting.
    use DatabaseTransactions;

    /**
     * This test ensures that the login page loads successfully and the login form is displayed
     * by checking that the page returns a 200 status code and contains the expected 'Login' text.
     */
    public function testLoginFormLoads(): void
    {
        // Act: Send a GET request to the login page route.
        // This simulates visiting the login page to verify its rendering.
        $response = $this->get(route('login'));

        // Assert: Verify that the page loads successfully with a 200 OK status.
        // The status code 200 indicates that the page is accessible and there are no errors.
        $response->assertStatus(200);

        // Assert: Verify that the 'Login', 'Email', and 'Password' text are present on the page.
        // This ensures that the login form is properly rendered with the expected content.
        $response->assertSee(['Login', 'Email', 'Password']);
    }

    /**
     * This test ensures that the login form validation behaves correctly under different failure scenarios,
     */
    public function testLoginValidationFails(): void
    {
        // Test 1: Empty fields (email, password)
        // Simulate submitting the login form with empty fields for both 'email' and 'password'.
        // This should trigger validation errors for both fields because they are required.
        $response = $this->post(route('login'), [
            'email' => '',
            'password' => '',
        ]);

        // Assert: Ensure that the response status is 302 (redirect) after submitting invalid data.
        // The user should be redirected back to the login form after failed validation.
        $response->assertStatus(302);

        // Assert: Check that the session contains validation errors for the 'email' and 'password' fields.
        // This confirms that the required validation for both fields is working as expected.
        $response->assertSessionHasErrors(['email', 'password']);

        // Test 2: Invalid email format
        // Simulate submitting the login form with an invalid email format.
        // This should trigger a validation error specifically for the 'email' field.
        $response = $this->post(route('login'), [
            'email' => 'invalid-email',  // Invalid email format
            'password' => 'validpassword123',  // password for this test case
        ]);

        // Assert: Ensure that the response status is still 302 (redirect) after submitting invalid email.
        // The user should be redirected back to the login form with validation errors.
        $response->assertStatus(302);

        // Assert: Check that the session contains a validation error for the 'email' field.
        // This confirms that the invalid email format was properly validated.
        $response->assertSessionHasErrors(['email']);

        // Test 3: Missing password
        // Simulate submitting the login form with a missing password.
        // This should trigger a validation error specifically for the 'password' field.
        $response = $this->post(route('login'), [
            'email' => 'user@example.com',  // Valid email
            'password' => '',  // Missing password
        ]);

        // Assert: Ensure that the response status is 302 (redirect) after submitting an incomplete form.
        // The user should be redirected back to the login form after the failed validation.
        $response->assertStatus(302);

        // Assert: Check that the session contains a validation error for the 'password' field.
        // This confirms that the required validation for the 'password' field is working as expected.
        $response->assertSessionHasErrors(['password']);
    }

    /**
     * This test simulates the login process for a user whose account status
     * is set to `User::STATUS_WAIT` (waiting for confirmation).
     */
    public function test_user_with_wait_status_can_not_proceed_to_protected_area(): void
    {
        $password = 'secret';
        // Arrange: Create a user with the 'status' set to 'WAIT' (indicating the user needs to confirm their email).
        // This uses the User factory to create the user with the 'status' explicitly set to 'WAIT' to simulate an unconfirmed account.
        $user = User::factory()->create([
            'email_verified_at' => Carbon::now()->subHours(48), // Simulate a verified email timestamp.
            'status' => User::STATUS_WAIT, // User needs confirmation to activate the account.
            'password' => bcrypt($password), // Match the password used in the test.
        ]);

        // Act: Attempt login with the user's credentials.
        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => $password,
        ]);

        // To confirm that the middleware behaves as expected, we simulate
        // a request to a route that is protected by the verified middleware.
        // This allows us to explicitly test if the middleware correctly detects
        // an unverified user and redirects them to the 'verification.notice route'.

        // Simulate middleware handling by making another request to a route requiring verification.
        $middlewareResponse = $this->get(route('cabinet.adverts.index'));
        // Assert: Middleware redirects to the verification notice page.
        $middlewareResponse->assertStatus(302)
            ->assertRedirect(route('verification.notice'));

        // Assert: The user is authenticated.
        $this->assertAuthenticatedAs($user);

        // Assert: Verify no side effects (e.g., user remains unconfirmed).
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => User::STATUS_WAIT,
        ]);
    }

    /**
     * This test simulates the login process for an active user. It creates a user with the 'status' set to
     * `User::STATUS_ACTIVE` using a factory. The test then attempts to log in with the created user's email
     * and password, and asserts that the response redirects to the correct route and that the user is authenticated.
     */
    public function testActiveUserCanLoginSuccessfullyWithRedirectionToDashboardAfterLogin(): void
    {
        // Arrange: Create a new user with the 'status' set to 'ACTIVE'.
        // This uses the User factory to create a user and explicitly sets the 'status' field to
        // User::STATUS_ACTIVE to simulate an active user in the system.
        $user = User::factory()->create([
            'email_verified_at' => Carbon::now()->subSeconds(300)->format('Y-m-d H:i:s'),
            'status' => User::STATUS_ACTIVE,
        ]);
        // Log::info($user->toArray());
        // Act: Simulate the user logging in using their email and password.
        // Here, we're making a POST request to the '/login' route with the user's email and password
        // to test if the active user can log in successfully.
        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'secret',
        ]);

        // Assert: Check the response status and redirection.
        // After a successful login attempt, the user should be redirected to the '/cabinet/adverts' route.
        // We assert that the response status code is 302 (Redirect) and that the user is redirected to the
        // expected route after logging in successfully.
        $response
            ->assertStatus(302)  // Ensure a 302 Redirect status code is returned.
            ->assertRedirect(route('cabinet.adverts.index'));  // Ensure the redirection is to the user's dashboard.

        // Assert: Verify that the user is authenticated after logging in.
        // This assertion checks that after the login attempt, the system recognizes the user as authenticated.
        // It ensures that the authentication logic is working properly for an active user.
        $this->assertAuthenticated();  // Confirm that the user is authenticated after the login request.
    }
}
