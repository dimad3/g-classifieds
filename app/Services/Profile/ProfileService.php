<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Http\Requests\Cabinet\ProfileEditRequest;
use App\Models\User\User;

/**
 * Class ProfileService
 *
 * Handles operations related to updating user profile information.
 */
class ProfileService
{
    /**
     * Updates the user's profile with new data from the request.
     *
     * Retrieves the user by ID, updates their profile information, and checks if the phone number has changed.
     * If the phone number is updated, it marks the phone as unverified.
     *
     * @param  int  $id  The ID of the user whose profile is being updated.
     * @param  ProfileEditRequest  $request  The validated request containing the new profile data.
     */
    public function update(int $id, ProfileEditRequest $request): void
    {
        /** @var User $user */
        // Find the user by ID or fail if the user does not exist
        $user = User::findOrFail($id);

        // Store the current phone number for comparison
        $oldPhone = $user->phone;

        // Update the user's profile with the new name, last name, and phone number
        $user->update($request->only('name', 'last_name', 'phone'));

        // If the phone number has changed, unverify the new phone number
        if ($user->phone !== $oldPhone) {
            $user->unverifyPhone();
        }
    }
}
