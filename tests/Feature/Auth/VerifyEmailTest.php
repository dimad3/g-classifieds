<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Test registration and email verification flow.
 */
class VerifyEmailTest extends TestCase
{
    // Use DatabaseTransactions to ensure each test runs within a transaction
    // that is rolled back after the test, preventing test data from persisting.
    use DatabaseTransactions;

    /**
     * Test successful email verification.
     */
    public function test_successful_email_verification(): void
    {
        // Arrange: Create a user who is not verified
        $user = User::factory()->create([
            'status' => User::STATUS_WAIT,
            'email_verified_at' => null,
        ]);
        //Log::info($user->toArray());

        // Authenticate the user
        $this->actingAs($user);

        // Generate the signed URL for email verification
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Act: Call the route
        $response = $this->get($verificationUrl);

        // Assert: Check the redirect based on the user's role
        if ($user->isAdmin()) {
            $response->assertRedirect(route('admin.dashboard'));
        } else {
            $response->assertRedirect(route('cabinet.adverts.index'));
        }

        // Assert: Check that email is verified and user is active (use fresh instance to reflect changes)
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertTrue($user->fresh()->isActive());
    }

    public function test_verification_fails_with_invalid_hash(): void
    {
        // Arrange: Create a user who is not verified
        $user = User::factory()->create([
            'status' => User::STATUS_WAIT,
            'email_verified_at' => null,
        ]);
        //Log::info($user->toArray());

        // Authenticate the user
        $this->actingAs($user);

        // Generate an invalid hash
        $invalidVerificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => 'invalid-hash']
        );

        // Act: Call the route
        $response = $this->get($invalidVerificationUrl);

        // Assert: Verification fails
        $response->assertForbidden();
        // Assert: Check that email is null and user is not active (use fresh instance to reflect changes)
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertFalse($user->fresh()->isActive());
    }

    public function test_verification_fails_with_expired_link(): void
    {
        // Arrange: Create a user who is not verified
        $user = User::factory()->create([
            'status' => User::STATUS_WAIT,
            'email_verified_at' => null,
        ]);
        //Log::info($user->toArray());

        // Authenticate the user
        $this->actingAs($user);

        // Generate an invalid hash
        $expiredVerificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->subSecond(), // URL that has already expired
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Act: Call the route
        $response = $this->get($expiredVerificationUrl);

        // Assert: Check that the response is (403)
        $response->assertStatus(403);

        // Assert: Check that email is null and user is not active (use fresh instance to reflect changes)
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertFalse($user->fresh()->isActive());
    }

    /**
     * Test that the verification.notice route is accessible by authenticated users.
     */
    public function testEmailVerificationNoticeIsAccessibleOnlyForAuthenticatedUsers(): void
    {
        // Arrange: Create a user who is not verified
        $user = User::factory()->create([
            'status' => User::STATUS_WAIT,
            'email_verified_at' => null,
        ]);
        //Log::info($user->toArray());

        // Authenticate the user
        $this->actingAs($user);

        // Act: Call the route for email verification notice
        $response = $this->get(route('verification.notice'));

        // Assert: The response should return a successful status and show the verification notice view
        $response->assertStatus(200);  // Assert the page loads successfully
        $response->assertViewIs('auth.verify');  // Ensure the correct view is returned
    }

    /**
     * Test that the verification.notice route redirects unauthenticated users.
     */
    public function testEmailVerificationNoticeRedirectsUnauthenticatedUsersToLoginPage(): void
    {
        // Act: Call the route for email verification notice without authenticating
        $response = $this->get(route('verification.notice'));

        // Assert: Unauthenticated users should be redirected to the login page
        $response->assertRedirect(route('login'));
    }

    /**
     * Test the email resend behavior for a verified user.
     */
    public function test_resend_verification_link_for_verified_user(): void
    {
        // Arrange: Create a user with a verified email
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(), // Mark the user's email as verified
        ]);

        // Determine the expected redirect path based on the user's role
        $expectedRedirectPath = $user->isAdmin()
            ? route('admin.dashboard') // Redirect admins to the admin dashboard
            : route('cabinet.adverts.index'); // Redirect regular users to their dashboard

        // Act: Simulate a POST request to the email resend route, acting as the verified user
        $response = $this->actingAs($user)->post(route('verification.resend'));

        // Assert: For non-JSON requests, it should redirect to the correct path
        $response->assertRedirect($expectedRedirectPath);
    }

    /**
     * Test the email resend behavior for a non-verified user.
     */
    public function test_resend_verification_link_for_non_verified_user(): void
    {
        // Arrange: Create a user without a verified email
        $user = User::factory()->create([
            'status' => User::STATUS_WAIT,
            'email_verified_at' => null, // Ensure the user's email is not verified
        ]);

        // Mock the notification to ensure it gets sent
        Notification::fake();

        // Act: Simulate a POST request to the email resend route, acting as the non-verified user
        $response = $this->actingAs($user)->post(route('verification.resend'));

        // Assert: Check that the verification email notification was sent
        Notification::assertSentTo($user, VerifyEmail::class);

        // Assert: If the request is not JSON, it should redirect back with a 'resent' flash message
        $response->assertRedirect()->with('resent', true);
    }

    /**
     * Test that an unauthenticated user cannot resend the email verification link.
     *
     * @return void
     */
    public function test_unauthenticated_user_cannot_resend_email_verification_link(): void
    {
        // Act: Send a POST request to the email/resend route without authentication
        $response = $this->post(route('verification.resend'));

        // Assert: Check that the unauthenticated user is redirected to the login page
        // The Authenticate middleware handles unauthenticated users and will by default
        // redirect them to the login page (/login) if they are not authenticated.
        $response->assertRedirect(route('login'));
    }

    public function testVerifiedMiddlewareRedirectsUnverifiedUsers(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => Carbon::now()->subHours(48), // Simulate a verified email timestamp.
            'status' => User::STATUS_WAIT, // User needs confirmation to activate the account.
        ]);

        $response = $this->actingAs($user)->get(route('cabinet.adverts.index'));

        // Assert: User is redirected to the verification notice page.
        $response->assertStatus(302)
            ->assertRedirect(route('verification.notice'));
    }
}
