<?php

declare(strict_types=1);

namespace App\Console\Commands\Elasticsearch;

use App\Models\Adverts\Advert\Advert;
use App\Services\Adverts\Elasticsearch\AdvertDocsIndexerService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| IndexAdverts
|--------------------------------------------------------------------------
|
| Purpose:
| This command retrieves adverts from your database (from the advert_adverts table)
| and indexes them into the Elasticsearch index.
| It effectively populates the index with the data so that you can search it later.
|
| Usage:
| Run this command whenever you want to index the current adverts from your database into Elasticsearch.
| This is particularly important after creating the index or after adding new adverts
| to ensure that your index is up-to-date.
|
*/

class RefreshAdvertsIndexCommand extends Command
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'elasticsearch:refresh-adverts';

    /** @var string The console command description. */
    protected $description = 'Removes all adverts from Elasticsearch and reindexes all adverts from the database into Elasticsearch.';

    protected AdvertDocsIndexerService $advertDocsIndexerService;

    public function __construct()
    {
        parent::__construct();

        $this->advertDocsIndexerService = app(AdvertDocsIndexerService::class);
    }

    /**
     * Execute the console command.
     *
     * Handles the bulk indexing of adverts in Elasticsearch.
     * - Clears all existing documents from the 'adverts' index.
     * - Processes and indexes all adverts from the database in chunks.
     * - Logs the number of adverts deleted, the number of processed chunks,
     *   the total number of database queries executed, and the execution time.
     *
     * @return int Returns 0 on successful execution.
     */
    public function handle(): int
    {
        $this->warn('Starting Elasticsearch adverts index refreshing...');

        try {
            if (! is_elasticsearch_running()) {
                $this->error('Elasticsearch service is NOT working!');

                return 1; // Exit with failure
            }

            // Measure the total execution time of the entire operation
            $executionTime = number_format(((Benchmark::measure(function (): void {
                // Enable database query logging to count the number of queries
                DB::enableQueryLog();

                // Step 1: Clear all documents from the 'adverts' index
                $this->warn("Clearing all documents from index 'adverts'...");
                $deletedCount = $this->advertDocsIndexerService->clearAllAdverts();
                $this->info("The total number of deleted adverts: {$deletedCount}");

                // Step 2: Process and index all adverts
                $this->warn('Processing and indexing adverts...');

                // Get the total count of adverts for display
                $totalAdvertsCount = Advert::active()->notExpired()->count();
                $this->info("Total adverts count: {$totalAdvertsCount}");

                $processedCount = 0; // Track how many adverts have been processed

                $this->advertDocsIndexerService->indexAllAdverts(function ($adverts) use ($totalAdvertsCount, &$processedCount): void {
                    // Increment the processed count
                    $processedCount += count($adverts);

                    // Log remaining adverts every 50,000 processed
                    if ($processedCount % 50000 === 0 || $processedCount >= $totalAdvertsCount) {
                        $remaining = max(0, $totalAdvertsCount - $processedCount);
                        $this->info("The rest of adverts to be indexed is: {$remaining}");
                    }

                    // Log the number of adverts processed in the current chunk
                    $this->info('Processed a chunk of ' . count($adverts) . ' adverts.');
                });

                // Step 3: Log the total number of database queries executed
                $queryCount = count(DB::getQueryLog());
                $this->info("Total number of database queries executed: {$queryCount}");
            })) / 1000), 2); // Convert execution time to seconds and format to two decimal places

            // Log the total execution time
            $this->info("Execution Time: {$executionTime} seconds");

            return 0; // Return 0 to indicate successful execution
        } catch (Exception $e) {
            // Handle unexpected errors
            Log::error("An unexpected Elasticsearch error occurred while refreshing the 'adverts' index: " . get_class($e) . " - {$e->getMessage()}");
            $this->error("An unexpected error occurred while refreshing the 'adverts' index.");

            return 2; // Return non-zero to indicate an error
        } finally {
            // Optional: Perform any cleanup actions
            DB::disableQueryLog(); // Disable query logging after execution
        }
    }
}
