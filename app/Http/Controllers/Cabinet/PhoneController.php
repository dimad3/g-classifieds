<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cabinet\PhoneVerifyRequest;
use App\Services\Profile\PhoneService;
use Illuminate\Support\Facades\Auth;

/**
 * Controller for handling phone-related functionality in the user's cabinet.
 *
 * This controller allows users to request phone verification, verify their phone number,
 * and enable or disable phone-based authentication.
 */
class PhoneController extends Controller
{
    private PhoneService $phoneService;

    /**
     * Injects the PhoneService to handle phone verification and phone auth functionality.
     *
     * @param  PhoneService  $phoneService  The service used to handle phone verification and auth.
     */
    public function __construct(PhoneService $phoneService)
    {
        $this->phoneService = $phoneService;
    }

    /**
     * This method generates a token, sets the expiration time in the database,
     * sends an SMS with the verification token, and then redirects the user to the phone verification form.
     *
     * @return \Illuminate\Http\RedirectResponse Redirects to the phone verification form.
     */
    public function requestPhoneVerificationToken()
    {
        try {
            // Calls the service to request a phone verification token and send it via SMS
            $this->phoneService->requestPhoneVerificationToken(Auth::id());
        } catch (\DomainException $e) {
            // If an error occurs, redirect back with error message
            return redirect()->back()->with('error', $e->getMessage());
        }

        // Redirect to the phone verification form after successful token generation
        return redirect()->route('cabinet.profile.phone.show_verification_token_form');
    }

    /**
     * Display the form where the user can enter the SMS token.
     *
     * @return \Illuminate\View\View The view for the phone verification form.
     */
    public function showVerificationTokenForm()
    {
        // Retrieves the authenticated user's information
        $user = Auth::user();

        // Return the view with the user data for phone verification
        return view('cabinet.profile.show_verify_phone', compact('user'));
    }

    /**
     * Verifies the phone number using the provided token.
     *
     * This method verifies the phone number by validating the token submitted by the user.
     * If verification is successful, the user is redirected to their profile.
     * If there is an error (e.g., invalid token), the user is redirected back to the form.
     *
     * @param  PhoneVerifyRequest  $request  The request containing the verification token.
     * @return \Illuminate\Http\RedirectResponse Redirects to the user's profile after successful verification.
     */
    public function verifyPhone(PhoneVerifyRequest $request)
    {
        try {
            // Verifies the phone number using the token from the request
            $this->phoneService->verifyPhone(Auth::id(), $request);
        } catch (\DomainException $e) {
            // If verification fails, redirect back to the show_verification_token_form form with the error message
            return redirect()->route('cabinet.profile.phone.show_verification_token_form')->with('error', $e->getMessage());
        }

        // Redirect to the profile page after successful phone verification
        return redirect()->route('cabinet.profile.show');
    }

    /**
     * Toggles phone-based authentication.
     *
     * This method enables or disables phone-based authentication for the user.
     * After the toggle, the user is redirected to their profile page.
     *
     * @return \Illuminate\Http\RedirectResponse Redirects to the user's profile after toggling phone auth.
     */
    public function togglePhoneAuth()
    {
        // Toggles phone authentication for the current user
        $this->phoneService->togglePhoneAuth(Auth::id());

        // Redirect back to the profile page after toggling phone auth status
        return redirect()->route('cabinet.profile.show');
    }
}
