<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\Adverts\AdvertDetailResource;
use App\Models\Adverts\Advert\Advert;
use App\Services\Adverts\FavoriteService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    private $favoriteService;

    public function __construct(FavoriteService $favoriteService)
    {
        $this->favoriteService = $favoriteService;
    }

    /**
     * @OA\Get(
     *     path="/api/user/favorites",
     *     tags={"My Favorites"},
     *     summary="Get the list of adverts",
     *     description="Returns the list of favorite adverts of Authenticated user",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Success response",
     *
     *         @OA\MediaType(
     *              mediaType="application/json",
     *         )
     *     ),
     *
     *     @OA\Response(response="default", description="An error has occurred.")
     * )
     */
    public function index()
    {
        $adverts = Advert::favoredByUser(Auth::user())->orderByDesc('published_at')->get();

        return AdvertDetailResource::collection($adverts);
    }

    /**
     * @OA\Post(
     *     path="/api/user/favorites/{id}",
     *     tags={"My Favorites"},
     *     summary="Mark advert as favorite",
     *     description="Add advert to the storage.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         description="ID of advert",
     *         required=true,
     *         in="path",
     *
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Resource has been created",
     *
     *         @OA\MediaType(
     *              mediaType="application/json",
     *         )
     *     ),
     *
     *     @OA\Response(response="default", description="An error has occurred.")
     * )
     */
    public function addToFavorites(Advert $advert)
    {
        $this->favoriteService->addToFavorites(Auth::id(), $advert->id);

        return response()->json([
            'success' => 'Advert was added to your favorites successfully.',
        ], Response::HTTP_CREATED);
    }

    /**
     * @OA\Delete(
     *     path="/api/user/favorites/{id}",
     *     tags={"My Favorites"},
     *     summary="Mark advert as not favorite",
     *     description="Remove advert from the storage.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         description="ID of advert",
     *         required=true,
     *         in="path",
     *
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Success response",
     *
     *         @OA\MediaType(
     *              mediaType="application/json",
     *         )
     *     ),
     *
     *     @OA\Response(response="default", description="An error has occurred.")
     * )
     */
    public function remove(Advert $advert)
    {
        $this->favoriteService->remove(Auth::id(), $advert->id);

        // return response()->json([
        //     'success' => 'Advert was removed from your favorites.'
        // ], Response::HTTP_NO_CONTENT);
        return response()->json([
            'success' => 'Advert was removed from favorites successfully .',
        ], Response::HTTP_OK);
    }
}
