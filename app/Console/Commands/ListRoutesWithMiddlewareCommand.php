<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class ListRoutesWithMiddlewareCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'route:list:with-middleware';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all routes with their assigned middleware';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Retrieve all routes
        $routes = Route::getRoutes();

        // Prepare table data with route details and middleware
        $tableData = collect($routes)->map(function ($route) {
            return [
                'Method' => implode('|', $route->methods()),  // Concatenate route methods (GET, POST, etc.)
                'URI' => $route->uri(),                        // Get the URI
                'Name' => $route->getName(),                    // Get the route name
                // Convert middleware to a string, in case it's an object or array
                'Middleware' => implode(', ', array_map(
                    function ($middleware) {
                        // Ensure the middleware is cast to a string (some middleware might be objects)
                        return is_object($middleware) ? get_class($middleware) : (string) $middleware;
                    },
                    $route->gatherMiddleware() // returns an array of all middleware assigned to the route.
                )),
            ];
        })->toArray();

        // Output the table with specified columns
        $this->table(['Method', 'URI', 'Name', 'Middleware'], $tableData);
    }
}
