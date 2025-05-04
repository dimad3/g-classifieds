<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Adverts\Elasticsearch\AdvertDocsIndexerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to index a collection of adverts in Elasticsearch.
 *
 * This job processes a given set of adverts and indexes them into the
 * Elasticsearch `adverts` index using the provided service. It supports
 * both `Collection` and array input formats.
 */
class BulkIndexAdvertsInElasticsearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int The number of times the job may be attempted. */
    public $tries = 3; // https://laravel.com/docs/10.x/queues#max-job-attempts-and-timeout

    /** @var Collection|array The collection or array of adverts to index. */
    protected Collection|array $adverts;

    /**
     * Initialize the job with a collection or array of adverts.
     *
     * @param  Collection|array  $adverts  The adverts to be indexed.
     */
    public function __construct(Collection|array $adverts)
    {
        $this->adverts = $adverts;
    }

    /**
     * Handle the job and perform bulk indexing.
     *
     * Uses the AdvertDocsIndexerService to index the adverts into Elasticsearch.
     * If any exception occurs, it logs the error and rethrows it to ensure
     * the job fails and can be retried by the queue worker.
     *
     * @param  AdvertDocsIndexerService  $advertDocsIndexerService  The service for managing Elasticsearch operations.
     *
     * @throws \Exception Rethrows any exceptions to trigger job retry logic.
     */
    public function handle(AdvertDocsIndexerService $advertDocsIndexerService): void
    {
        try {
            // Call the service to perform bulk indexing of adverts
            $advertDocsIndexerService->bulkIndexAdverts($this->adverts);
        } catch (\Exception $e) {
            // Log the error with relevant context
            Log::error('The Job failed to index adverts in Elasticsearch', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
