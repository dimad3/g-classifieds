<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\NetworkService;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class NetworkController extends Controller
{
    private $networkService;

    public function __construct(NetworkService $networkService)
    {
        $this->networkService = $networkService;
    }

    public function redirect(string $network)
    {
        // dd(Socialite::driver($network)->redirect());
        /**
         * redirect() method - redirect the user to the OAuth provider
         * Redirect the user to the authentication page for the provider.
         *
         * @return \Illuminate\Http\RedirectResponse
         */
        return Socialite::driver($network)->redirect();
    }

    /**
     * Handle authentication callback
     *
     * @return RedirectResponse
     */
    public function callback(string $network)
    {
        // dd(Socialite::driver($network));
        /**
         * user() method - read the incoming request and retrieve the user's information
         * from the provider after they are authenticated
         * Get the User instance for the authenticated user.
         *
         * @return \Laravel\Socialite\One\User
         *
         * @throws \Laravel\Socialite\One\MissingVerifierException
         */
        $networkUser = Socialite::driver($network)->user();

        // dd($networkUser);
        try {
            $user = $this->networkService->auth($network, $networkUser);
            Auth::login($user);

            return redirect()->intended();
        } catch (\DomainException $e) {
            return redirect()->route('login')->with('error', $e->getMessage());
        }
    }
}
