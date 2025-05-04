<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Region;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FixRegionTree implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
    }

    /**
     * Execute the job
     * After code changing restart php artisan queue:work
     */
    public function handle(): void
    {
        if (Region::isBroken()) {
            Region::fixTree();
        }
    }
}
