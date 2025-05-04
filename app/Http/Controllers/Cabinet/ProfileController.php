<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cabinet\ProfileEditRequest;
use App\Services\Profile\ProfileService;
use Illuminate\Support\Facades\Auth;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * Class ProfileController
 *
 * Manages the display, editing, and updating of the authenticated user's profile information.
 */
class ProfileController extends Controller
{
    /**
     * @var ProfileService Service for managing profile data operations.
     */
    private ProfileService $profileService;

    /**
     * ProfileController constructor.
     *
     * @param  ProfileService  $profileService  Service responsible for handling profile updates.
     */
    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    /**
     * Display the user's profile information.
     *
     * Retrieves the currently authenticated user's data and passes it to the profile view.
     *
     * @return \Illuminate\View\View The view displaying the user's profile information.
     */
    public function show()
    {
        // Retrieve the authenticated user's information
        $user = Auth::user();

        // Uncomment below if additional phone processing is needed
        // $phone = new PhoneNumber($user->phone);
        // $phone = phone($user->phone);
        // dd($phone->getCountry());

        // Return the profile view with the user's data
        return view('cabinet.profile.show', compact('user'));
    }

    /**
     * Display the form for editing the user's profile.
     *
     * Retrieves the authenticated user's current data and passes it to the edit profile view.
     *
     * @return \Illuminate\View\View The view containing the form to edit profile information.
     */
    public function edit()
    {
        // Retrieve the authenticated user's information
        $user = Auth::user();

        // Return the edit profile view with the user's data
        return view('cabinet.profile.edit', compact('user'));
    }

    /**
     * Update the user's profile with new information.
     *
     * Uses the ProfileService to update the user's profile based on input from the edit form.
     * If an error occurs, it redirects back with an error message.
     *
     * @param  ProfileEditRequest  $request  Validated request data for updating the profile.
     * @return \Illuminate\Http\RedirectResponse Redirects to profile view on success, or back with error on failure.
     */
    public function update(ProfileEditRequest $request)
    {
        try {
            // Update the profile using the ProfileService
            $this->profileService->update(Auth::id(), $request);
        } catch (\DomainException $e) {
            // Redirect back with error if update fails
            return redirect()->back()->with('error', $e->getMessage());
        }

        // Redirect to the profile view on successful update
        return redirect()->route('cabinet.profile.show');
    }
}
