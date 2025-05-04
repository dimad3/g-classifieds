<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

class HomeController
{
    /**
     * @OA\Get(
     *     path="/api",
     *     tags={"Adverts Panel"},
     *     summary="About This Site",
     *
     *     @OA\Response(
     *         response="200", description="Successful operation",
     *
     *         @OA\MediaType(
     *              mediaType="application/json"
     *         )
     *     ),
     *
     *     @OA\Response(response="default", description="An error has occurred.")
     * )
     */
    public function home()
    {
        return [
            'name' => 'Adverts Panel API',
        ];
    }
}
