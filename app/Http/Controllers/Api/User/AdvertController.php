<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adverts\PhotosRequest;
use App\Http\Requests\Adverts\StoreRequest;
use App\Http\Resources\Adverts\AdvertDetailResource;
// use App\Http\Requests\Adverts\AttributesRequest;
use App\Http\Resources\Adverts\AdvertListResource;
use App\Models\Adverts\Advert\Advert;
use App\Models\Adverts\Category;
use App\Models\Region;
use App\Services\Adverts\AdvertService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdvertController extends Controller
{
    private $advertService;

    public function __construct(AdvertService $advertService)
    {
        $this->advertService = $advertService;
    }

    /**
     * @OA\Get(
     *     path="/api/user/adverts",
     *     tags={"My Adverts"},
     *     summary="Get list of adverts",
     *     description="Returns list of adverts which belong to Authenticated user",
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
    public function index()
    {
        $adverts = Advert::forUser(Auth::user())->orderByDesc('published_at')->paginate(20);

        return AdvertListResource::collection($adverts);
    }

    /**
     * @OA\Get(
     *     path="/api/user/adverts/{id}",
     *     tags={"My Adverts"},
     *     summary="Get specific user's advert",
     *     description="Returns specific advert which belong to Authenticated user",
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
     *     @OA\Response(response="default", description="An error has occurred."),
     * )
     */
    public function show(Advert $advert)
    {
        $this->checkAccess($advert);
        $advert = $advert
            ->loadMissing([
                'category:id,name',
                'user:id,name',
                'activePhotos',
                'attributesWithValues' => function ($query): void {
                    $query->select('name', 'type');
                },
            ]);

        return new AdvertDetailResource($advert);
    }

    // 24.03.2024 - todo
    // public function store(StoreRequest $request, Category $category, Region $region = null)
    // {
    //     $advert = $this->advertService->storeAdvert(
    //         Auth::id(),
    //         $category->id,
    //         $region ? $region->id : null,
    //         $request
    //     );
    //     return (new AdvertDetailResource($advert))
    //         ->response()
    //         ->setStatusCode(Response::HTTP_CREATED);
    // }

    // 24.03.2024 - todo
    // public function update(StoreRequest $request, Advert $advert)
    // {
    //     $this->checkAccess($advert);
    //     $this->advertService->updateAdvert($advert, $request);
    //     return new AdvertDetailResource(Advert::findOrFail($advert->id));
    // }

    /**
     * @OA\Delete(
     *     path="/api/user/adverts/{id}",
     *     tags={"My Adverts"},
     *     summary="Delete advert",
     *     description="Remove an advert from the storage.",
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
     *     @OA\Response(response="default", description="An error has occurred."),
     * )
     */
    public function destroy(Advert $advert)
    {
        $this->checkAccess($advert);
        $this->advertService->destroy($advert->id);

        // return response()->json([], Response::HTTP_NO_CONTENT);
        return response()->json([
            'success' => 'Advert was removed successfully .',
        ], Response::HTTP_OK);
    }

    // 24.03.2024 - todo
    // public function photos(PhotosRequest $request, Advert $advert)
    // {
    //     $this->checkAccess($advert);
    //     $this->advertService->addPhotos($advert->id, $request);
    //     return new AdvertDetailResource(Advert::findOrFail($advert->id));
    // }

    /**
     * @OA\Post(
     *     path="/api/user/adverts/{id}/send-to-moderation",
     *     tags={"My Adverts"},
     *     summary="Update advert's status",
     *     description="Update advert's status to 'moderation'",
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
     *     @OA\Response(response="default", description="An error has occurred."),
     * )
     */
    public function sendToModeration(Advert $advert)
    {
        $this->checkAccess($advert);
        $this->advertService->sendToModeration($advert->id);

        return new AdvertDetailResource(Advert::findOrFail($advert->id));
    }

    /**
     * @OA\Post(
     *     path="/api/user/adverts/{id}/close",
     *     tags={"My Adverts"},
     *     summary="Update advert's status",
     *     description="Update advert's status to 'closed'",
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
    public function close(Advert $advert)
    {
        $this->checkAccess($advert);
        $this->advertService->close($advert->id);

        return new AdvertDetailResource(Advert::findOrFail($advert->id));
    }

    /**
     * @OA\Post(
     *     path="/api/user/adverts/{id}/restore",
     *     tags={"My Adverts"},
     *     summary="Update advert's status",
     *     description="Update advert's status to 'draft'",
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
     *     @OA\Response(response="default", description="An error has occurred."),
     * )
     */
    public function restore(Advert $advert)
    {
        $this->checkAccess($advert);
        $this->advertService->restore($advert->id);

        return new AdvertDetailResource(Advert::findOrFail($advert->id));
    }

    // HELPER sub-methods ==================================

    private function checkAccess(Advert $advert): void
    {
        // can not be injected in constructor because this middleware accepts two parameters
        // so the middleware can be called only from method where $advert argument is accessable
        if (! Gate::allows('manage-own-advert', $advert)) {
            // if just abort - not possible to handle?
            // abort(Response::HTTP_FORBIDDEN);

            // for handling HTTP_FORBIDDEN response
            throw new AccessDeniedHttpException();
        }
    }
}
