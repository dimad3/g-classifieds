<?php

declare(strict_types=1);

/**
 * Elasticsearch Configuration
 *
 * This configuration file returns an array of settings for connecting to the
 * Elasticsearch service. It defines the hosts that the application will use
 * to interact with the Elasticsearch server.
 */

return [
    /**
     * Array of Elasticsearch hosts
     *
     * The 'hosts' key holds an array of Elasticsearch server endpoints.
     * The application will use these hosts to connect to the Elasticsearch instance.
     *
     * The value is retrieved from the environment variable ELASTICSEARCH_HOST,
     * with a default value of 'http://localhost:9200' if the variable is not set.
     */
    'hosts' => [
        // Retrieve the Elasticsearch host from the environment variable,
        // or use 'http://localhost:9200' as the default value.
        env('ELASTICSEARCH_HOST', 'http://localhost:9200'),
    ],
];
