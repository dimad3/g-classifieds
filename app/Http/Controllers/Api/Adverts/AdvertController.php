<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Adverts;

use App\Http\Controllers\Controller;
use App\Http\Resources\Adverts\AdvertDetailResource;
use App\Http\Resources\Adverts\AdvertListResource;
use App\Http\Router\AdvertsPath;
// use App\Http\Requests\Adverts\SearchRequest;
use App\Models\Adverts\Advert\Advert;
use App\Models\Adverts\Category;
use App\Models\Region;
// use App\Services\Adverts\SearchService;
use Illuminate\Support\Facades\Gate;

class AdvertController extends Controller
{
    // private $search;

    // public function __construct(SearchService $search)
    // {
    //     $this->search = $search;
    // }

    // todo: elasticsearch not implemented

    /**
     * @OA\Get(
     *     path="/api/adverts",
     *     tags={"Adverts Panel"},
     *     summary="Get the list of adverts",
     *     description="Returns the list of adverts if the request is authenticated",
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
    // public function index(SearchRequest $request)
    // public function index(AdvertsPath $path)
    public function index()
    {
        // $region = $request->get('region') ? Region::findOrFail($request->get('region')) : null;
        // $category = $request->get('category') ? Category::findOrFail($request->get('category')) : null;

        // $result = $this->search->search($category, $region, $request, 20, $request->get('page', 1));

        $query = Advert::active()->notExpired()->orderByDesc('published_at')->with(['region', 'category']);

        // todo: routes are NOT set for {advert_path} parameter
        // if ($category = $path->category) {
        //     $query->forCategory($category);
        // }

        // if ($region = $path->region) {
        //     $query->forRegion($region);
        // }

        $adverts = $query->paginate(20);
        // $adverts = $query->get();

        // return AdvertListResource::collection($result->adverts);
        return AdvertListResource::collection($adverts);
    }

    /**
     * @OA\Get(
     *     path="/api/adverts/{id}",
     *     tags={"Adverts Panel"},
     *     summary="Get advert information",
     *     description="Returns advert data if the request is authenticated",
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
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Success response",
     *
     *         @OA\MediaType(
     *              mediaType="application/json",
     *         )
     *     ),
     *
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Access Denied"),
     *     @OA\Response(response=404, description="Resource not found"),
     *     @OA\Response(response=500, description="Internal Server Error"),
     * )
     */
    public function show(Advert $advert)
    {
        // https://laraveldaily.com/post/laravel-api-override-404-error-message-route-model-binding

        // when advert is not active only admin, moderator and author (user) can see it
        if (! ($advert->isActive() || Gate::allows('show-advert', $advert))) {
            abort(404);
        }
        $advert = $advert
            // ->select(['id', 'title', 'content', 'published_at', 'expires_at'])
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
}
