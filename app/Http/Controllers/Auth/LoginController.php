<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User\User;
use App\Services\Sms\SmsSender;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class LoginController
 *
 * This controller handles user authentication, including login, logout,
 * and optional two-factor phone verification.
 */
class LoginController extends Controller
{
    // The ThrottlesLogins trait in Laravel is used to control and limit login attempts
    // to prevent brute-force attacks on user accounts.
    use ThrottlesLogins;

    /** @var SmsSender SMS service for sending verification codes */
    private $sms;

    /**
     * Initialize the LoginController instance.
     *
     * @param  SmsSender  $sms  SMS service dependency
     */
    public function __construct(SmsSender $sms)
    {
        // Apply the guest middleware to all methods except 'logout' to ensure only guests can access login actions.
        $this->middleware('guest')->except('logout');
        $this->sms = $sms;
    }

    /**
     * Display the login form view.
     *
     * @return \Illuminate\View\View The login form view
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Process a login request.
     *
     * Authenticates the user using email and password. Handles scenarios where
     * the user account is not confirmed or requires phone-based two-factor
     * authentication. Throttles login attempts to prevent abuse.
     *
     * @param  LoginRequest  $request  Validated login request data
     *
     * @throws ValidationException If login fails or account is unverified
     */
    public function login(LoginRequest $request)
    {
        if ($this->hasTooManyLoginAttempts($request)) {
            // Handle login throttling if too many attempts are detected
            $this->fireLockoutEvent($request);
            $this->sendLockoutResponse($request);
        }

        /**
         * Attempt to authenticate the user with the provided credentials.
         *
         * @var bool $authenticate True if authentication is successful, false otherwise.
         */
        $isAuthenticated = Auth::attempt(
            $request->only(['email', 'password']),
            $request->filled('remember')
        );

        if ($isAuthenticated) {
            // Regenerate session to prevent session fixation.
            // Regenerating the session ID changes the session identifier,
            // making it difficult for an attacker to predict or set.
            $request->session()->regenerate();
            // Clear the login locks for the given user credentials.
            $this->clearLoginAttempts($request);

            /** @var User $user */
            $user = Auth::user();

            // Check if the user has phone-based two-factor authentication enabled
            if ($user->isPhoneAuthEnabled()) {
                Auth::logout();

                // Generate and store a token for SMS verification
                $token = (string) random_int(10000, 99999);
                $request->session()->put('auth', [
                    // 'id' => $user->id,
                    'id' => $user->id,
                    'token' => $token,
                    'remember' => $request->filled('remember'),
                ]);

                // Send the SMS token to the user’s phone (commented out for testing)
                $this->sms->send($user->phone, 'Login code: ' . $token);

                // Redirect to phone verification page
                return redirect()->route('login.phone');
            }

            // Redirect to intended destination after successful login
            return redirect()
                // intended() - is a method in Laravel that directs the user to the
                // originally requested URL before they were redirected to the login page.
                // route('cabinet.adverts.index') - this is the fallback URL.
                // If the user doesn’t have an intended URL (meaning they accessed the login page directly).
                ->intended(route('cabinet.adverts.index'));
        }

        // If authentication fails, increment login attempts and throw an exception
        $this->incrementLoginAttempts($request);
        throw ValidationException::withMessages(['email' => [trans('auth.failed')]]);
    }

    /**
     * Log out the authenticated user and invalidate the session.
     *
     * @param  Request  $request  Current HTTP request instance
     * @return \Illuminate\Http\RedirectResponse Redirect to the home page
     */
    public function logout(Request $request)
    {
        // Logout user from the current session
        Auth::guard()->logout();

        // Invalidate and regenerate the session to prevent session reuse
        $request->session()->invalidate();

        return redirect()->route('home');
    }

    /**
     * Display the form for SMS-based login.
     *
     * This method returns the view that presents the user with a form
     * to enter a verification code sent to their phone, used as part of
     * the SMS-based login process. The form allows the user to input
     * the token they received for two-factor authentication.
     *
     * @return \Illuminate\View\View The view that contains the phone verification form.
     */
    public function showLoginWithSmsForm()
    {
        return view('auth.login_with_sms_token');
    }

    /**
     * Process phone-based login request using the provided token.
     *
     * Authenticates the user using the SMS token sent to the user for two-factor authentication.
     * Logs the user in if the token matches, or increments the login attempt count
     * if the token is invalid. Throttles repeated invalid attempts.
     *
     * @param  Request  $request  Current HTTP request with token
     *
     * @throws ValidationException If the verification token is invalid
     * @throws BadRequestHttpException If session auth data is missing
     */
    public function loginWithSmsToken(Request $request)
    {
        if ($this->hasTooManyLoginAttempts($request)) {
            // Handle login throttling if too many attempts are detected
            $this->fireLockoutEvent($request);
            $this->sendLockoutResponse($request);
        }

        // Validate the provided token is a non-empty string
        $this->validate($request, [
            'token' => 'required|string',
        ]);

        // Retrieve the auth session data, or throw an error if it’s missing
        if (! $session = $request->session()->get('auth')) {
            throw new BadRequestHttpException('Missing token info.');
        }

        /** @var User $user */
        $user = User::findOrFail($session['id']);

        // Check if the provided token matches the one in the session
        if ($request['token'] === $session['token']) {
            // Clear session and login attempts, then authenticate the user
            $request->session()->flush();
            $this->clearLoginAttempts($request);
            Auth::login($user, $session['remember']);

            return redirect()->intended(route('cabinet.adverts.index'));
        }

        // Increment attempts if the token is incorrect and throw an error
        $this->incrementLoginAttempts($request);
        throw ValidationException::withMessages(['token' => ['Invalid auth token.']]);
    }

    /**
     * Get the login username used by the controller.
     *
     * @return string Field name for the login credential ('email' by default)
     */
    protected function username()
    {
        return 'email';
    }
}
