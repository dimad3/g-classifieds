<?php

declare(strict_types=1);

namespace App\Console\Commands\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Illuminate\Console\Command;

class CountDocumentsCommand extends Command
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'elasticsearch:document-count {index}';

    /** @var string The console command description. */
    protected $description = 'Get the document count from an Elasticsearch index';

    /** @var Client Elasticsearch client instance. */
    protected $client;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        // Initialize Elasticsearch client
        $this->client = app('Elasticsearch');
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            if (! is_elasticsearch_running()) {
                $this->error('Elasticsearch service is NOT working!');

                return 1; // Exit with failure
            }

            // Get the index name from the command argument
            $index = $this->argument('index');

            // Set up parameters for the count API
            $params = [
                'index' => $index,
                'body' => [
                    'query' => [
                        'match_all' => new \stdClass(),  // This counts all documents in the index
                    ],
                ],
            ];

            // Execute the count query
            $response = $this->client->count($params);

            // Output the document count
            $documentCount = $response['count'];
            $this->info("Document count in index '{$index}': {$documentCount}");

            return 0; // Command executed successfully
        } catch (\Exception $e) {
            // Catch and log any errors
            $this->error('Error getting document count: ' . $e->getMessage());

            return 2; // Command failed
        }
    }
}
