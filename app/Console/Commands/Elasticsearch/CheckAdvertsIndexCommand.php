<?php

declare(strict_types=1);

namespace App\Console\Commands\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Illuminate\Console\Command;

class CheckAdvertsIndexCommand extends Command
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'elasticsearch:check-adverts';

    /** @var string The console command description. */
    protected $description = 'Check Elasticsearch index data for "adverts" index';

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

            $indexName = 'adverts';

            // Check if the index exists
            $params = [
                'index' => $indexName,
            ];

            if (! $this->client->indices()->exists($params)) {
                $this->error("Index '{$indexName}' does not exist.");

                return 2;
            }

            // Query data from the 'adverts' index
            $searchParams = [
                'index' => $indexName,
                'body' => [
                    'query' => [
                        'match_all' => new \stdClass(),  // Fetch all documents
                    ],
                ],
            ];

            $response = $this->client->search($searchParams);

            $this->info("Found {$response['hits']['total']['value']} documents in the '{$indexName}' index.");
            $this->info('Elasticsearch returns a maximum of 10 hits (documents) by default.');
            dump($response['hits']['hits']);
        } catch (\Exception $e) {
            $this->error('Error querying Elasticsearch: ' . $e->getMessage());

            return 3; // Failure
        }

        return 0; // Success
    }
}
