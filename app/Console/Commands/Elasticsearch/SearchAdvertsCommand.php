<?php

declare(strict_types=1);

namespace App\Console\Commands\Elasticsearch;

use App\Services\Adverts\Elasticsearch\SearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SearchAdvertsCommand extends Command
{
    protected $searchService;

    /** @var string The name and signature of the console command. */
    protected $signature = 'elasticsearch:search-adverts {query}';

    /** @var string The console command description. */
    protected $description = 'Search for adverts in Elasticsearch based on a query';

    public function __construct(SearchService $searchService)
    {
        parent::__construct();

        $this->searchService = $searchService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->warn('Starting Elasticsearch adverts index refreshing...');

        try {
            if (! is_elasticsearch_running()) {
                $this->error('Elasticsearch service is NOT working!');

                return 1; // Exit with failure
            }

            $query = $this->argument('query'); // Get the search query from the command argument

            // Perform the search
            $response = $this->searchService->searchAdverts($query);

            // Display results
            if ($response['hits']['total']['value'] > 0) {
                $this->info("Found {$response['hits']['total']['value']} adverts:");
                foreach ($response['hits']['hits'] as $hit) {
                    $this->line("Advert ID: {$hit['_id']}");
                    $this->line("Title: {$hit['_source']['title']}");
                    $this->line("Content: {$hit['_source']['content']}");
                    $this->line(str_repeat('-', 64));
                }
            } else {
                $this->info('No adverts found.');
            }
        } catch (\Exception $e) {
            $this->error('An error occurred while searching adverts: ' . $e->getMessage());
            Log::error('Elasticsearch searching error: ' . get_class($e) . " - {$e->getMessage()}");

            return 2;
        }

        return 0;
    }
}
