<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Adverts\Category;
use App\Models\Region;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Cache;

class CacheServiceProvider extends ServiceProvider
{
    private $classes = [
        Region::class,
        Category::class,
    ];

    public function boot(): void
    {
        foreach ($this->classes as $class) {
            $this->registerFlusher($class);
        }
    }

    private function registerFlusher($class): void
    {
        // `use` allows you to access (use) the succeeding variables inside the closure
        // Without `use`, function cannot access parent scope variable
        // https://stackoverflow.com/questions/1065188/in-php-what-is-a-closure-and-why-does-it-use-the-use-identifier
        $flush = function () use ($class): void {
            Cache::tags($class)->flush();
        };

        /** @var Model $class */
        $class::created($flush);
        $class::saved($flush);
        $class::updated($flush);
        $class::deleted($flush);
    }
}
