<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LoginController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/login",
     *     tags={"Auth"},
     *     summary="Authenticate user and generate token",
     *     description="Handle an incoming authentication request. An access token is then created for that user on successfull authentication.",
     *
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="User's email",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="password",
     *         in="query",
     *         description="User's password",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(response="200", description="Login successful"),
     *     @OA\Response(response="401", description="Failed to authenticate.")
     * )
     */
    public function login(LoginRequest $request)
    {
        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
        ];

        // attempt($user) - Log a user into the application without sessions or cookies
        if (auth()->attempt($credentials)) {
            // successfull authentication
            $user = auth()->user();

            /**
             * Create a new personal access token for the user.
             * createToken($name, array $scopes = [])
             * C:\laragon\www\ads2\vendor\laravel\passport\src\HasApiTokens.php
             *
             * @param  string  $name  - the name of the token (any string)
             * @param  array  $scopes  - https://laravel.com/docs/10.x/passport#token-scopes
             * @return \Laravel\Passport\PersonalAccessTokenResult
             */
            $token = $user->createToken('token_name_can_be_any_string')->accessToken;

            // Success response is returned containing the token and user model instance.
            // Status code 200 indicates the request succeeded and was OK.
            return response()->json([
                'success' => 'Login successful.',
                'access_token' => $token,
                'user' => $user,
            ], Response::HTTP_OK);
        }

        // If that user is not found or the credentials being sent are incorrect
        // from what is on the users table on the database, a failure response is returned.
        // Status code 401 indicates the client is unauthenticated.
        return response()->json([
            'error' => 'Failed to authenticate.',
        ], Response::HTTP_UNAUTHORIZED);

    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     tags={"Auth"},
     *     summary="Log out the user from application.",
     *     description="Revokes earlier created access token for the `user`",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response="200", description="Logout successful"),
     *     @OA\Response(response="default", description="An error has occurred.")
     * )
     */
    public function logout()
    {
        $user = auth()->user();
        // check if the user making the request is authenticated,
        if ($user) {
            // revokes earlier created access token for that `user`
            // The revoke() method simply sets revoked `column` on `oauth_access_tokens` table from 0 to 1
            // for that `user ID`. `0` here means token not revoked. `1` means token has been revoked.
            // token() - get the current access token being used by the user.
            $user->token()->revoke();

            // Success response is returned. Status code 200 indicates the request succeeded and was OK
            return response()->json([
                'success' => 'User is logged out successfully',
            ], 200);
        }
    }
}
