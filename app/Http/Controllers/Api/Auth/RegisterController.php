<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\RegisterService;
use Illuminate\Http\Response;

class RegisterController extends Controller
{
    private $registerService;

    public function __construct(RegisterService $registerService)
    {
        $this->registerService = $registerService;
    }

    /**
     * @OA\Post(
     *     path="/api/register",
     *     tags={"Auth"},
     *     summary="Register a new user",
     *
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="User's name",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
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
     *     @OA\Parameter(
     *         name="password_confirmation",
     *         in="query",
     *         description="User's password confirmation",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(response="201", description="User registered successfully. Check your email and click on the link to verify.",
     *
     *         @OA\MediaType(
     *              mediaType="application/json",
     *         )
     *     ),
     *
     *     @OA\Response(response="422", description="Validation errors"),
     *     @OA\Response(response="500", description="Internal Server Error"),
     *     @OA\Response(response="default", description="An error has occurred.")
     * )
     */
    public function register(RegisterRequest $request)
    {
        $this->registerService->register($request);
        // registerService->register($request) does NOT return User
        // $token = auth()->user()->createToken('any_string')->accessToken;

        return response()->json([
            'success' => 'User registered successfully. Check your email and click on the link to verify.',
        ], Response::HTTP_CREATED);
    }
}
