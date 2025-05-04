<?php

declare(strict_types=1);

namespace App\Console\Commands\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Illuminate\Console\Command;
use Illuminate\Support\Benchmark;

/**
 * Class CreateAdvertsIndex
 *
 * This class creates the "adverts" index in Elasticsearch if it doesn't exist.
 * If the index exists, it deletes the existing one first and then recreates it.
 */
class CreateAdvertsIndexCommand extends Command
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'elasticsearch:create-adverts-index';

    /** @var string The console command description. */
    protected $description = 'Create Elasticsearch index for adverts with appropriate mappings';

    /** @var Client Elasticsearch client instance. */
    protected Client $client;

    public function __construct()
    {
        parent::__construct();

        // Initialize Elasticsearch client
        $this->client = app('Elasticsearch');
    }

    /**
     * Execute the console command.
     * https://chatgpt.com/c/671a550e-238c-800f-80b8-bf2b9e3e035f
     *
     * @return int
     */
    public function handle()
    {
        // Start Elasticsearch
        $this->warn('Starting Elasticsearch inexing...');

        if (! is_elasticsearch_running()) {
            $this->error('Elasticsearch service is NOT working!');

            return 1; // Exit with failure
        }

        $executionTime = number_format(((Benchmark::measure(function () {
            // Check if the 'adverts' index exists
            $indexExists = $this->client->indices()->exists(['index' => 'adverts'])->asBool();

            // If index exists, delete it
            if ($indexExists) {
                // Try to delete the existing index
                try {
                    $this->client->indices()->delete(['index' => 'adverts']);
                    $this->info('Previous Index "adverts" was deleted.');
                } catch (ClientResponseException $e) {
                    // If the index couldn't be deleted due to some Elasticsearch-specific issue, log the error
                    $this->error('Error deleting the index: ' . $e->getMessage());

                    return 2;  // Stop if the index could not be deleted
                } catch (ElasticsearchException $e) {
                    $this->error('Elasticsearch error: ' . $e->getMessage());

                    return 3;  // Exit if an unexpected Elasticsearch error occurs
                } catch (\Exception $e) {
                    // Catch any other unforeseen exceptions
                    $this->error('An unexpected error occurred: ' . $e->getMessage());

                    return 4;  // Stop the script in case of an error
                }
            } else {
                // If index does not exist, log a message and proceed to create it
                $this->info('Index "adverts" does not exist. Proceeding to create a new one.');
            }

            // Define the index mappings and settings
            $params = [
                'index' => 'adverts',  // Name of the index to be created in Elasticsearch
                'body' => [
                    'mappings' => [  // Define the mapping (schema) of the index
                        'properties' => [  // Define the fields (columns) in the index
                            'id' => [
                                'type' => 'integer',  // The 'id' field is stored as an integer, typically used as a unique identifier
                            ],
                            'title' => [
                                'type' => 'text',  // The 'title' field is stored as a text type, enabling full-text search capabilities
                                'fields' => [
                                    'raw' => [
                                        'type' => 'keyword',  // The 'title.raw' field is stored as a keyword, allowing exact matches (no tokenization, e.g., for sorting or filtering)
                                    ],
                                ],
                            ],
                            'content' => [
                                'type' => 'text',  // The 'content' field allows full-text search, suitable for longer text fields that need analysis
                                'fields' => [
                                    'raw' => [
                                        'type' => 'keyword',  // The 'content.raw' field allows exact match searches, useful when you want to filter or sort based on the exact content value
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            // Create the index
            try {
                $response = $this->client->indices()->create($params);
                $this->info('Elasticsearch index "adverts" created successfully!');
                $this->info(json_encode($response->asArray(), JSON_PRETTY_PRINT));
            } catch (ElasticsearchException $e) {
                $this->error('Elasticsearch error: ' . $e->getMessage());

                return 5;
            } catch (\Exception $e) {
                $this->error('An unexpected error occurred: ' . $e->getMessage());

                return 6;
            }

            return 0;  // Exit successfully
        })) / 1000), 2);
        $this->info("Execution Time: {$executionTime} seconds");
    }
}
