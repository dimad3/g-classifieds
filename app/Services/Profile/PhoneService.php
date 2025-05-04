<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Http\Requests\Cabinet\PhoneVerifyRequest;
use App\Models\User\User;
use App\Services\Sms\SmsSender;
use Carbon\Carbon;

/**
 * Service class responsible for managing user phone verification.
 *
 * This service provides methods to handle phone number verification for users, including:
 * - Requesting a phone verification token and sending it via SMS.
 * - Verifying the phone number with the provided token.
 * - Toggling the phone-based authentication setting for the user.
 */
class PhoneService
{
    /** @var SmsSender */
    private $sms;

    /**
     * PhoneService constructor.
     *
     * @param  SmsSender  $sms  SMS sender service for sending verification tokens.
     */
    public function __construct(SmsSender $sms)
    {
        $this->sms = $sms;
    }

    /**
     * Request phone verification for a user by generating a token and sending it via SMS.
     *
     * This method generates a phone verification token for the user and stores it in the database.
     * Then, it sends the token to the user via SMS.
     *
     * @param  int  $id  User's ID to request phone verification for.
     */
    public function requestPhoneVerificationToken(int $id): void
    {
        // Retrieve the user by ID
        /** @var User */
        $user = $this->getUser($id);

        // Generates a verification token and expiration time, and stores them in the database for the user's phone verification.
        $token = $user->requestPhoneVerificationToken(Carbon::now());

        // Send the generated token to the user via SMS (commented out for testing)
        // $this->sms->send($user->phone, 'Phone verification token: ' . $token);
    }

    /**
     * Verify a user's phone number using in the request provided token.
     *
     * This method verifies the user's phone number by checking if the provided token matches the one stored.
     * If the token is valid and not expired, the user's phone is marked as verified.
     *
     * @param  int  $id  User's ID to verify the phone number for.
     * @param  PhoneVerifyRequest  $request  The request containing the token to verify.
     */
    public function verifyPhone(int $id, PhoneVerifyRequest $request): void
    {
        // Retrieve the user by ID
        /** @var User */
        $user = $this->getUser($id);

        // Attempt to verify the phone using the provided token and current time
        $user->verifyPhone($request['token'], Carbon::now());
    }

    /**
     * Toggle the phone-based authentication setting for a user.
     *
     * This method enables or disables phone-based authentication for the user.
     * If it is currently enabled, it will be disabled and vice versa.
     *
     * @param  int  $id  User's ID to toggle phone authentication for.
     * @return bool The current status of phone-based authentication (true if enabled, false if disabled).
     */
    public function togglePhoneAuth(int $id): bool
    {
        // Retrieve the user by ID
        /** @var User */
        $user = $this->getUser($id);

        // Toggle phone authentication setting based on the current state
        if ($user->isPhoneAuthEnabled()) {
            $user->disablePhoneAuth();
        } else {
            $user->enablePhoneAuth();
        }

        // Return the updated status of phone authentication
        return $user->isPhoneAuthEnabled();
    }

    // Helpers ===================================================

    /**
     * Retrieve the user by their ID, or throw an exception if the user doesn't exist.
     *
     * @param  int  $id  The ID of the user to retrieve.
     * @return User The retrieved user object.
     */
    private function getUser(int $id): User
    {
        // Fetch user by ID, will throw exception if not found
        return User::findOrFail($id);
    }
}
