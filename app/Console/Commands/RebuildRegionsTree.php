<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Region;
use App\Services\RegionService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RebuildRegionsTree extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'regionstree:rebuild';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild the Nested Set tree for the Region model';

    public function handle(RegionService $regionService)
    {
        try {
            $this->info('Starting to rebuild the nested set tree for the Region model...');

            // Call fixTree() to rebuild the tree
            Region::fixTree();
            // Let Laravel's service container resolve the service here
            $regionService->RebuildRegionsPaths();

            $this->info('Nested set tree for the Region model has been rebuilt successfully!');

            return 0; // Success exit code
        } catch (Exception $e) {
            // Log the error
            Log::error('Error rebuilding the nested set tree', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);

            // Display error message to the console
            $this->error('An error occurred while rebuilding the nested set tree: ' . $e->getMessage());

            // Optionally provide more details on the console
            $this->line('Please check the log file for more details.');

            return 1; // General error exit code
        }
    }
}
