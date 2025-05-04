<?php

declare(strict_types=1);

namespace App\Services\Adverts\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

class SearchService
{
    protected Client $client;

    public function __construct()
    {
        $this->client = ClientBuilder::create()
            // This ensures that your code doesn't retry indefinitely when there’s an issue with the connection
            ->setRetries(2) // Limit retries to 2
            ->build();
    }

    /**
     * Search for adverts in the Elasticsearch index using a given query string.
     * https://chatgpt.com/c/671a550e-238c-800f-80b8-bf2b9e3e035f
     * https://chatgpt.com/c/67212847-b9b0-800f-9a08-2c7fdfced33a
     *
     * @param  string  $query  The search term to be queried in Elasticsearch.
     * @param  int  $size  The number of results per page.
     * @param  int  $from  The offset for pagination.
     * @return Elasticsearch|Promise Returns either an Elasticsearch response object
     *                               containing the search results, or a Promise if the request is asynchronous.
     */
    public function searchAdverts(string $query, int $size = 10, int $from = 0)
    {
        // Define the search parameters for Elasticsearch
        $params = [
            'index' => 'adverts',  // The index to search in
            'body' => [
                'query' => [
                    'bool' => [  // Combining multiple query types with a boolean condition
                        'should' => [  // At least one of these conditions must match
                            // Full-text search using phrase_prefix
                            [
                                'multi_match' => [
                                    'query' => "{$query}",  // The search query
                                    'fields' => ['title', 'content', 'categories', 'regions'],  // Fields to search in
                                    'type' => 'phrase_prefix',  // Allows prefix matching for phrases
                                ],
                            ],
                            // Wildcard search for sub-string matches in the 'title' field
                            [
                                'wildcard' => [
                                    'title.raw' => "*{$query}*",  // Use wildcards to match partial strings
                                ],
                            ],
                            // Wildcard search for sub-string matches in the 'content' field
                            [
                                'wildcard' => [
                                    'content.raw' => "*{$query}*",  // Use wildcards to match partial strings
                                ],
                            ],
                            // Wildcard search for sub-string matches in the 'categories' field
                            [
                                'wildcard' => [
                                    'categories.raw' => "*{$query}*",  // Use wildcards to match partial strings
                                ],
                            ],
                            // Wildcard search for sub-string matches in the 'regions' field
                            [
                                'wildcard' => [
                                    'regions.raw' => "*{$query}*",  // Use wildcards to match partial strings
                                ],
                            ],
                        ],
                    ],
                ],

                // Add highlighting with custom tags
                'highlight' => [
                    'fields' => [
                        'title' => [
                            'pre_tags' => ['<span class="highlight">'],
                            'post_tags' => ['</span>'],
                        ],
                        'content' => [
                            'pre_tags' => ['<span class="highlight">'],
                            'post_tags' => ['</span>'],
                        ],
                        'categories' => [
                            'pre_tags' => ['<span class="highlight">'],
                            'post_tags' => ['</span>'],
                        ],
                        'regions' => [
                            'pre_tags' => ['<span class="highlight">'],
                            'post_tags' => ['</span>'],
                        ],
                    ],
                ],
                'size' => $size, // set the number of results to return
                'from' => $from, // Start position for pagination
            ],
        ];

        // Execute the search query using the Elasticsearch client
        $response = $this->client->search($params);

        // Return the search results
        return $response;
    }
}
