<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Adverts\AdvertController as AdvertsAdvertController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\User\AdvertController as UserAdvertController;
use App\Http\Controllers\Api\User\FavoriteController;
use App\Http\Controllers\Api\User\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
| Routes defined in the routes/api.php file are nested within a route group by the RouteServiceProvider.
| Within this group, the /api URI prefix is automatically applied so you do not need to manually apply it
| to every route in the file
|
*/

Route::group(
    ['as' => 'api.'],
    function (): void {
        Route::get('/', [HomeController::class, 'home']);

        // Public routes of authtication
        Route::post('/register', [RegisterController::class, 'register']);
        Route::post('/login', [LoginController::class, 'login']);

        // Protected routes of advert and logout
        // The middleware('auth:api') - makes routes to be protected by authentication guard
        // for valid access tokens on incoming requests
        Route::middleware('auth:api')->group(function (): void {
            Route::post('logout', [LoginController::class, 'logout']);

            Route::resource('adverts', AdvertsAdvertController::class)->only('index', 'show');

            Route::group(
                [
                    'prefix' => 'user',
                    'as' => 'user.',
                ],
                function (): void {
                    Route::get('/', [ProfileController::class, 'show']);
                    Route::put('/', [ProfileController::class, 'update']);

                    Route::resource('adverts', UserAdvertController::class)->only('index', 'show', 'update', 'destroy');
                    // 24.03.2024 - todo
                    // Route::post('/adverts/create/{category}/{region?}', [UserAdvertController::class, 'store']);
                    // Route::put('/adverts/{advert}/photos', [UserAdvertController::class, 'photos']);
                    // Route::put('/adverts/{advert}/attributes', [UserAdvertController::class, 'attributes']);

                    Route::post('/adverts/{advert}/send-to-moderation', [UserAdvertController::class, 'sendToModeration']);
                    Route::post('/adverts/{advert}/close', [UserAdvertController::class, 'close']);
                    Route::post('/adverts/{advert}/restore', [UserAdvertController::class, 'restore']);

                    Route::get('/favorites', [FavoriteController::class, 'index']);
                    Route::post('/favorites/{advert}', [FavoriteController::class, 'addToFavorites']);
                    Route::delete('/favorites/{advert}', [FavoriteController::class, 'remove']);
                }
            );
        });
    }
);
