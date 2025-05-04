<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Router\AdvertsPath;
use App\Http\Router\PagePath;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application. This is used by Laravel authentication to redirect users after login.
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        // bind the AdvertsPath class to the route parameter
        // model() method - specifies the class for a given parameter.
        // You should define your explicit model bindings at the beginning of the boot method of your RouteServiceProvider class
        // Purpose: This tells Laravel to use the AdvertsPath class to resolve the adverts_path parameter from the route.
        // https://laravel.com/docs/8.x/routing#explicit-binding
        // https://www.digitalocean.com/community/tutorials/cleaner-laravel-controllers-with-route-model-binding#custom-exceptions-for-route-model-binding
        Route::model('adverts_path', AdvertsPath::class);   // Bind AdvertsPath Object to the route's parameter 'adverts_path'
        Route::model('page_path', PagePath::class);         // Bind PagePath Object to the route's parameter 'page_path'

        $this->configureRateLimiting();

        $this->routes(function (): void {
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60);
        });
    }
}
