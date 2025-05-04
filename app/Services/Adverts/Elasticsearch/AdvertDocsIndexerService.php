<?php

declare(strict_types=1);

namespace App\Services\Adverts\Elasticsearch;

use App\Models\Adverts\Advert\Advert;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Http\Promise\Promise;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AdvertDocsIndexerService
{
    // Performing `is_elasticsearch_running()` the Check in the Service Class explined
    // here: https://chatgpt.com/c/674b00c2-2688-800f-a616-66ceb6dcbd56
    protected Client $client;

    public function __construct()
    {
        $this->client = app('Elasticsearch');
    }

    /**
     * Indexes an advert in the Elasticsearch 'adverts' index.
     *
     * This method constructs the indexing parameters,
     * and sends the advert data to the Elasticsearch client for storage.
     *
     * @param  Advert  $advert  The advert object to be indexed.
     * @return Elasticsearch|Promise
     */
    public function indexAdvert(Advert $advert)
    {
        // Prepare the parameters for indexing the advert in Elasticsearch
        $params = [
            'index' => 'adverts', // The Elasticsearch index name where the advert will be stored
            'id' => $advert->id, // The unique identifier for the advert, ensuring it's updated if it already exists
            'body' => [
                'title' => $advert->title, // The title of the advert
                'content' => $advert->content, // The content or description of the advert
            ],
        ];

        // Index the advert document into Elasticsearch using the specified parameters
        $this->client->index($params);
    }

    /**
     * Deletes a document from the 'adverts' index by its ID.
     *
     * @param  int  $id  The ID of the document to delete.
     */
    public function deleteAdvert(int $id): void
    {
        $params = [
            'index' => 'adverts',  // Name of the index
            'id' => $id,             // Document ID to check and delete
        ];

        try {
            // Check if the document exists in the 'adverts' index
            $exists = $this->client->exists($params)->asBool();

            if (! $exists) {
                // Log::info("Advert with ID: {$id} does not exist in the 'adverts' index. Skipping delete.");
                return; // Exit if the document doesn't exist
            }

            // Proceed to delete if the document exists
            $this->client->delete($params);
        } catch (\Exception $e) {
            // Log the error message with context
            Log::error("Failed to delete advert ID: {$id}", [
                'exception' => [
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    // 'message' => $e->getMessage(),
                ],
            ]);
        }
    }

    /**
     * Process and index all adverts in chunks.
     *
     * @param  callable|null  $callback  Optional callback to process each advert chunk.
     * @param  int  $chunkSize  The number of adverts to process per chunk. Default is 1000.
     */
    public function indexAllAdverts(?callable $callback = null, int $chunkSize = 1000): void
    {
        Advert::active()
            ->notExpired()
            // ->where('id', '<', 20001) // enable andcustom it to index only a part of adverts
            ->chunkById($chunkSize, function ($advertsChunk) use ($callback): void {
                $params = ['body' => []]; // Initialize the bulk request payload
                foreach ($advertsChunk as $advert) {
                    // Add index metadata for each advert
                    $params['body'][] = [
                        'index' => [
                            '_index' => 'adverts', // Target index in Elasticsearch
                            '_id' => $advert->id,  // Unique identifier for the advert
                        ],
                    ];

                    // Add the document body for indexing
                    $params['body'][] = [
                        'title' => $advert->title,   // Advert title
                        'content' => $advert->content, // Advert content
                    ];
                }

                if (! empty($params['body'])) {
                    // Execute the bulk operation for the current chunk
                    try {
                        $response = $this->client->bulk($params);
                        if (! empty($response['errors'])) {
                            \Log::error('Errors while indexing adverts', ['errors' => $response['items']]);
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error during Elasticsearch bulk index', ['message' => $e->getMessage()]);
                    }
                }

                $params = ['body' => []]; // erase the old bulk request
                unset($response); // Free memory used by the current chunk

                // Execute callback if provided
                if ($callback) {
                    $callback($advertsChunk);
                }
            });
    }

    /**
     * Bulk indexes adverts into Elasticsearch in chunks.
     *
     * This method processes a collection of adverts, splits them into manageable chunks,
     * and indexes them into the Elasticsearch `adverts` index. Each chunk is handled
     * in a single bulk operation for efficiency. Errors during indexing are logged.
     *
     * @param  Collection|array  $adverts  adverts to be indexed.
     * @param  int  $chunkSize  The number of adverts to process per chunk. Default is 1000.
     */
    public function bulkIndexAdverts(Collection|array $adverts, int $chunkSize = 1000): void
    {
        // Measure execution time of the entire indexing process
        $executionTime = number_format(((Benchmark::measure(function () use ($adverts, $chunkSize): void {
            dump('Memory usage (on start): ' . memory_get_usage(true) / 1024 / 1024 . ' MB');
            dump('Prepearing adverts...');
            if (is_array($adverts)) {
                // Fetch adverts as a Collection of Advert model instances
                $advertIds = collect($adverts)->pluck('id'); // Extract the IDs of the inserted adverts
                $adverts = Advert::select('id', 'title', 'content', 'status', 'expires_at')->whereIn('id', $advertIds)->get();
            }

            // Display the total number of adverts to be indexed
            dump("The total number of adverts to be indexed: {$adverts->count()}");

            // Filter active, non-expired adverts and split them into smaller chunks
            $advertsChunks = $adverts
                ->where('status', 'active') // Only index adverts with 'active' status
                ->where('expires_at', '>', now()) // Exclude adverts that are expired
                ->chunk($chunkSize); // Split into chunks of size $chunkSize

            dump('Memory usage (adverts prepared): ' . memory_get_usage(true) / 1024 / 1024 . ' MB');

            // Notify about the beginning of the indexing process
            dump('Processing and indexing adverts...');

            // Iterate through each chunk of adverts
            foreach ($advertsChunks as $advertsChunk) {
                dump('Memory usage (before chunk processing start): ' . memory_get_usage(true) / 1024 / 1024 . ' MB');
                $params = ['body' => []]; // Initialize the bulk request payload

                foreach ($advertsChunk as $advert) {
                    // Add index metadata for each advert
                    $params['body'][] = [
                        'index' => [
                            '_index' => 'adverts', // Target index in Elasticsearch
                            '_id' => $advert->id,  // Unique identifier for the advert
                        ],
                    ];

                    // Add the document body for indexing
                    $params['body'][] = [
                        'title' => $advert->title,   // Advert title
                        'content' => $advert->content, // Advert content
                    ];
                }

                if (! empty($params['body'])) {
                    // Execute the bulk operation for the current chunk
                    try {
                        $response = $this->client->bulk($params);

                        dump('Processed a chunk of ' . count($advertsChunk) . ' adverts.');
                        dump('Memory usage (after chunk is processed): ' . memory_get_usage(true) / 1024 / 1024 . ' MB');
                        if (! empty($response['errors'])) {
                            \Log::error('Errors while indexing adverts', ['errors' => $response['items']]);
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error during Elasticsearch bulk index', ['message' => $e->getMessage()]);
                    }

                    // 28.11.2024 - The memory usage is the same before and after garbage collecton
                    $params = ['body' => []]; // erase the old bulk request
                    unset($response); // Free memory used by the current chunk
                    dump('Memory usage (atter garbage collecton): ' . memory_get_usage(true) / 1024 / 1024 . ' MB');

                    // 28.11.2024 - The memory usage is the same before and after garbage collecton
                    // dump('Memory usage (before garbage collecton): ' . memory_get_usage(true) / 1024 / 1024 . ' MB');
                    // unset($params); // Free memory used by the current chunk
                    // gc_collect_cycles(); // Explicitly collect garbage
                    // dump('Memory usage (atter garbage collecton): ' . memory_get_usage(true) / 1024 / 1024 . ' MB');
                }
            }
        })) / 1000), 2); // Convert execution time to seconds and format to 2 decimal places

        // Output the total execution time
        dump("Execution Time: {$executionTime} seconds");
    }

    /**
     * Clear all adverts from the Elasticsearch index.
     *
     * This method uses the `deleteByQuery` API to remove all documents from
     * the 'adverts' index in Elasticsearch. It executes a query that matches
     * all documents. If successful, it returns the number of deleted documents.
     * In case of an error, it logs the error and returns 0.
     *
     * @return int The number of documents deleted from the index, or 0 if an error occurred.
     */
    public function clearAllAdverts(): int
    {
        try {
            // Execute a deleteByQuery request to remove all documents from the 'adverts' index
            $response = $this->client->deleteByQuery([
                'index' => 'adverts', // Specify the index to clear
                'body' => [
                    'query' => [
                        'match_all' => new \stdClass(), // Query to match all documents
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            // Log an error if the deletion fails, including the error message and code
            Log::error('Error clearing the index', [
                'exception' => [
                    'message' => $e->getMessage(), // Log the error message
                    'code' => $e->getCode(),       // Log the error code
                ],
            ]);

            return 0; // Return 0 if an error occurred during deletion
        }

        // Return the number of deleted documents, or 0 if the response does not include the 'deleted' key
        return isset($response['deleted']) ? $response['deleted'] : 0; // Ensure safe access to the response
    }

    /**
     * Update an advert document.
     */
    // public function updateAdvert(Advert $advert, iterable $categories, iterable $regions): void
    // {
    // }
}
