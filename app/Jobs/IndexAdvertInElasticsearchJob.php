<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Adverts\Advert\Advert;
use App\Services\Adverts\Elasticsearch\AdvertDocsIndexerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job to index a single advert in Elasticsearch.
 *
 * This job is dispatched to queue an indexing task for a specific advert,
 * leveraging the Elasticsearch service for efficient search indexing.
 */
class IndexAdvertInElasticsearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int The number of times the job may be attempted. */
    public $tries = 5; // https://laravel.com/docs/10.x/queues#max-job-attempts-and-timeout

    /** @var Advert The advert instance to be indexed. */
    protected Advert $advert;

    /**
     * Create a new job instance.
     *
     * @param  Advert  $advert  The advert model instance to be indexed.
     */
    public function __construct(Advert $advert)
    {
        $this->advert = $advert;
    }

    /**
     * Execute the job to index the advert in Elasticsearch.
     *
     * @param  AdvertDocsIndexerService  $advertDocsIndexerService  The service responsible for handling Elasticsearch indexing.
     */
    public function handle(AdvertDocsIndexerService $advertDocsIndexerService): void
    {
        // Call the indexing service to index the advert.
        $advertDocsIndexerService->indexAdvert($this->advert);
    }
}
