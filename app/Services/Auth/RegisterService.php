<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Mail\Mailer;

/**
 * Class RegisterService
 *
 * Handles user registration, email verification, and event dispatching related to user registration.
 */
class RegisterService
{
    /**
     * @var Mailer Instance of the mailer to send verification emails.
     */
    private Mailer $mailer;

    /**
     * @var Dispatcher Event dispatcher instance for emitting registration events.
     */
    private Dispatcher $dispatcher;

    /**
     * RegisterService constructor.
     *
     * @param  Mailer  $mailer  Service to send emails.
     * @param  Dispatcher  $dispatcher  Service to dispatch events.
     */
    public function __construct(Mailer $mailer, Dispatcher $dispatcher)
    {
        $this->mailer = $mailer;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Handle the user registration process.
     *
     * @param  RegisterRequest  $request  The request object containing the registration data.
     * @return User The newly registered user.
     */
    public function register(RegisterRequest $request): User
    {
        // Create a new user with the provided name, email, and password
        $user = User::register(
            $request['name'],
            $request['email'],
            $request['password']
        );

        // Dispatch the Registered event to trigger any associated listeners
        // This event allows other parts of the system to act upon the user registration
        // (e.g., sending a verification email, logging, etc.)
        $this->dispatcher->dispatch(new Registered($user));

        // Return the newly created user instance
        return $user;
    }

    /**
     * Verify the email of a user by their ID.
     *
     * @param  int  $id  The user's ID to verify.
     */
    public function verifyEmail(int $id): void
    {
        /** @var User $user */
        $user = User::findOrFail($id);

        // Update the user's email verification status to 'active'
        $user->verifyEmail();
    }
}
