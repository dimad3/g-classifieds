<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cabinet\ProfileEditRequest;
use App\Http\Resources\User\ProfileResource;
use App\Models\User\User;
use App\Services\Profile\ProfileService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    private $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    /**
     * @OA\Get(
     *     path="/api/user",
     *     tags={"Auth"},
     *     summary="Get loged-in user data",
     *     description="Returns information about the loged-in user",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Success response",
     *
     *         @OA\MediaType(
     *              mediaType="application/json",
     *         )
     *     ),
     *
     *     @OA\Response(response="default", description="An error has occurred."),
     * )
     */
    public function show(Request $request)
    {
        return new ProfileResource($request->user());
    }

    /**
     * @OA\Put(
     *     path="/api/user",
     *     tags={"Auth"},
     *     summary="Update current user",
     *     description="todo",
     *     security={{"bearerAuth":{}}},
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
     *         name="last_name",
     *         in="query",
     *         description="User's last name",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="phone",
     *         in="query",
     *         description="User's phone number",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(response=200, description="Success response",
     *
     *         @OA\MediaType(
     *              mediaType="application/json",
     *         )
     *     ),
     *
     *     @OA\Response(response="default", description="An error has occurred."),
     * )
     */
    public function update(ProfileEditRequest $request)
    {
        $this->profileService->update($request->user()->id, $request);

        /** @var User $user */
        $user = User::findOrFail($request->user()->id);

        return new ProfileResource($user);
    }
}
