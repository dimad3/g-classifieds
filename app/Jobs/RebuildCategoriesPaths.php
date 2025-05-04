<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Adverts\CategoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RebuildCategoriesPaths implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        // No need to inject CategoryService here
        // see chatGpt: 09.10.2024 Service Injection in Jobs - https://chatgpt.com/c/6706766a-4694-800f-a93b-d7bae9bd0acb
    }

    /**
     * Execute the job - Build the tree of categories' paths
     * After code changing restart php artisan queue:work
     */
    public function handle(CategoryService $categoryService): void
    {
        // Let Laravel's service container resolve the service here
        $categoryService->rebuildCategoriesPaths();
    }
}
