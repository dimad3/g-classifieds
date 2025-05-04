<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Banners\CostCalculator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // https://laravel.com/docs/8.x/container#binding-a-singleton
        // https://stackoverflow.com/questions/49348681/what-is-a-usage-and-purpose-of-laravels-binding
        // Within a service provider, you always have access to the container via the $this->app property
        // We can register a binding using the singleton() method, passing the class or interface name that we wish to register
        // along with a closure that returns an instance of the class.
        // The singleton method binds a class or interface into the container that should only be resolved one time.
        $this->app->singleton(CostCalculator::class, function (Application $app) {
            $config = $app->make('config')->get('banner');
            // dd($config);    // ["price" => 0.01]
            $costCalculator = new CostCalculator($config['price']);

            return $costCalculator; // new CostCalculator($config['price']);
        });

        // Passport::ignoreMigrations();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrap();
        // Prevent lazy loading, but only when the app is not in production.
        Model::preventLazyLoading(! $this->app->isProduction());    // https://planetscale.com/blog/laravels-safety-mechanisms#n-1-prevention
        // Model::preventAccessingMissingAttributes(); // https://planetscale.com/blog/laravels-safety-mechanisms#partially-hydrated-model-protection

        // Enforce a morph map instead of making it optional.
        // https://laravel-news.com/enforcing-morph-maps-in-laravel
        // https://planetscale.com/blog/laravels-safety-mechanisms#polymorphic-mapping-enforcement

        // after php artisan ide-helper:models ->
        // -> Error resolving relation model of App\Models\User\User:notifications() : No morph map defined for model [App\Models\User\User].\User].
        // https://ralphjsmit.com/laravel-fix-no-morph-map-defined
        // Relation::enforceMorphMap([
        //     // 'user' => \App\User::class,
        //     // 'post' => \App\Post::class,
        // ]);
    }
}
