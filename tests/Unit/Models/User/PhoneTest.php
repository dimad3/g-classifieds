<?php

declare(strict_types=1);

namespace Tests\Unit\Models\User;

use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PhoneTest extends TestCase
{
    // Use DatabaseTransactions to ensure each test runs within a transaction
    // that is rolled back after the test, preventing test data from persisting.
    use DatabaseTransactions;

    /**
     * Test the default state of phone verification.
     */
    public function testIsPhoneNotVerifiedByDefault(): void
    {
        // Arrange: Create a user with no phone and verification details
        /** @var User $user */
        $user = User::factory()->create([
            'phone' => null,
            'phone_verified' => false,
            'phone_verify_token' => null,
        ]);

        // Assert: Ensure the user's phone is not verified
        self::assertFalse($user->isPhoneVerified());
    }

    /**
     * Test that an exception is thrown when trying to request phone verification with an empty phone.
     */
    public function testRequestPhoneVerificationWithEmptyPhone(): void
    {
        // Arrange: Create a user with no phone
        /** @var User $user */
        $user = User::factory()->create([
            'phone' => null,
            'phone_verified' => false,
            'phone_verify_token' => null,
        ]);

        // Assert: Expect exception when trying to request phone verification with no phone
        // expectExceptionMessage tells PHPUnit that the upcoming code
        // is expected to throw an exception with a specific message.
        // This must be done before the line of code that triggers the exception
        // so that PHPUnit knows what to expect.
        $this->expectExceptionMessage('Phone number is empty.');

        // Act: Attempt to request phone verification; this should throw an exception due to missing phone number.
        // This line of code that actually triggers the exception is run afterwar expectExceptionMessage()
        // allowing PHPUnit to verify if the exception was indeed thrown as expected.
        $user->requestPhoneVerificationToken(Carbon::now());
    }

    /**
     * Test requesting phone verification with a valid phone number.
     */
    public function testRequestPhoneVerificationWithValidButNotVerifiedPhone(): void
    {
        // Arrange: Create a user with a phone number
        /** @var User $user */
        $user = User::factory()->create([
            'phone' => '+37100000000',
            'phone_verified' => false,
            'phone_verify_token' => null,
        ]);

        // Act: Request phone verification
        $user->requestPhoneVerificationToken(Carbon::now());

        // Assert: Ensure phone is not verified yet and a token is generated
        self::assertFalse($user->isPhoneVerified());
        // self::assertNotEmpty($token);
        self::assertNotEmpty($user->phone_verify_token);
    }

    /**
     * Test requesting phone verification with an already verified phone.
     */
    public function testRequestPhoneVerificationAfterPhoneChanging(): void
    {
        // Arrange: Create a user with a verified phone
        /** @var User $user */
        $user = User::factory()->create([
            'phone' => '+37100000000',
            'phone_verified' => true,
            'phone_verify_token' => null,
        ]);

        // Assert: Ensure the phone is already verified
        self::assertTrue($user->isPhoneVerified());

        // Act: Request phone verification
        $user->requestPhoneVerificationToken(Carbon::now());

        // Assert: Ensure phone is no longer verified and token is generated
        self::assertFalse($user->isPhoneVerified());
        self::assertNotEmpty($user->phone_verify_token);
    }

    /**
     * Test requesting phone verification when a token has already been sent recently.
     */
    public function testPhoneVerificationTokenAlreadySent(): void
    {
        // Arrange: Create a user with a verified phone
        /** @var User $user */
        $user = User::factory()->create([
            'phone' => '+37100000000',
            'phone_verified' => false,
            'phone_verify_token' => null,
        ]);

        // Act: Request phone verification once
        $user->requestPhoneVerificationToken($now = Carbon::now());

        // Assert: Expect exception when trying to request verification again within 15 seconds
        // expectExceptionMessage tells PHPUnit that the upcoming code
        // is expected to throw an exception with a specific message.
        // This must be done before the line of code that triggers the exception
        // so that PHPUnit knows what to expect.
        $this->expectExceptionMessage('Token is already requested.');

        // Act: Attempt to request phone verification; this should throw an exception due to the token is already requested.
        // This line of code that actually triggers the exception is run afterwar expectExceptionMessage()
        // allowing PHPUnit to verify if the exception was indeed thrown as expected.
        $user->requestPhoneVerificationToken($now->copy()->addSeconds(15));
    }

    /**
     * Test successful phone verification.
     */
    public function testVerifyPhoneWithValidAndNonExpiredToken(): void
    {
        // Arrange: Create a user with a phone and a verification token
        /** @var User $user */
        $user = User::factory()->create([
            'phone' => '+37100000000',
            'phone_verified' => false,
            'phone_verify_token' => $token = 'token',
            'phone_verify_token_expire' => $now = Carbon::now(),
        ]);

        // Assert: Ensure the phone is not verified yet
        self::assertFalse($user->isPhoneVerified());

        // Act: Verify phone with the correct token
        $user->verifyPhone($token, $now->copy()->subSeconds(15));

        // Assert: Ensure phone is verified after correct token verification
        self::assertTrue($user->isPhoneVerified());
    }

    /**
     * Test unsuccessful phone verification due to incorrect token.
     */
    public function testVerifyPhoneWithInvalidToken(): void
    {
        // Arrange: Create a user with a phone and a verification token
        /** @var User $user */
        $user = User::factory()->create([
            'phone' => '+37100000000',
            'phone_verified' => false,
            'phone_verify_token' => 'token',
            'phone_verify_token_expire' => $now = Carbon::now(),
        ]);

        // Assert: Expect exception when trying to verify the phone with invalid token
        // expectExceptionMessage tells PHPUnit that the upcoming code
        // is expected to throw an exception with a specific message.
        // This must be done before the line of code that triggers the exception
        // so that PHPUnit knows what to expect.
        $this->expectExceptionMessage('Invalid verify token.');

        // Act: Try to verify with an incorrect token; this should throw an exception due to invalid token.
        // This line of code that actually triggers the exception is run afterwar expectExceptionMessage()
        // allowing PHPUnit to verify if the exception was indeed thrown as expected.
        $user->verifyPhone('other_token', $now->copy()->subSeconds(15));
    }

    /**
     * Test unsuccessful phone verification due to token expiration.
     */
    public function testVerifyPhoneWithExpiredToken(): void
    {
        // Arrange: Create a user with a phone and a verification token
        /** @var User $user */
        $user = User::factory()->create([
            'phone' => '+37100000000',
            'phone_verified' => false,
            'phone_verify_token' => $token = 'token',
            'phone_verify_token_expire' => $now = Carbon::now(),
        ]);

        // Assert: Expect exception when trying to verify the phone with expired token
        // expectExceptionMessage tells PHPUnit that the upcoming code
        // is expected to throw an exception with a specific message.
        // This must be done before the line of code that triggers the exception
        // so that PHPUnit knows what to expect.
        $this->expectExceptionMessage('Token is expired.');

        // Act: Try to verify with an expired token; this should throw an exception due to expired token.
        // This line of code that actually triggers the exception is run afterwar expectExceptionMessage()
        // allowing PHPUnit to verify if the exception was indeed thrown as expected.
        $user->verifyPhone($token, $now->copy()->addSeconds(500));
    }
}
