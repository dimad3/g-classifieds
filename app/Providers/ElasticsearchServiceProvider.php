<?php

declare(strict_types=1);

namespace App\Providers;

use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;

/**
 * Class ElasticsearchServiceProvider
 *
 * This service provider is responsible for registering the Elasticsearch client
 * within the Laravel application. It utilizes the singleton pattern to ensure
 * that only one instance of the Elasticsearch client is created and shared
 * throughout the application.
 */
class ElasticsearchServiceProvider extends ServiceProvider
{
    /**
     * Register the Elasticsearch client in the service container.
     *
     * This method binds a singleton instance of the Elasticsearch client to the application container.
     * It ensures that the client is instantiated once and shared throughout the application, providing
     * efficient and centralized access to the Elasticsearch service.
     *
     * The client configuration is dynamically retrieved from the application's configuration files,
     * allowing for flexibility and easy modification of the Elasticsearch hosts.
     *
     * @return void just bind service to the Laravel service container (not directly return a value)
     *              The actual client (\Elastic\Elasticsearch\Client) is returned when the service is resolved from the container,
     *              which typically happens elsewhere in the application when you use app('Elasticsearch')
     *              or inject the client into a class.
     */
    public function register(): void
    {
        // Bind the 'Elasticsearch' key to a singleton instance of the Elasticsearch client
        $this->app->singleton('Elasticsearch', function ($app) {
            // Retrieve the Elasticsearch configuration settings
            $config = config('elasticsearch');

            // ClientBuilder::create(): This is the factory method provided by the Elasticsearch PHP client,
            // which creates and configures the client
            return ClientBuilder::create()
                // setHosts(): This method sets the hosts of the Elasticsearch server
                // (from the Laravel config/elasticsearch.php config file).
                // Typically, the host might look like localhost:9200 or a cloud-based Elasticsearch service.
                ->setHosts($config['hosts'])
                ->build(); // Build and return the client instance
        });
    }

    /**
     * Bootstrap services.
     *
     * This method is called after all service providers have been registered.
     * It is used to perform actions needed during the bootstrapping of services.
     *
     * @return void
     */
    public function boot(): void
    {
        // You can perform bootstrapping actions here, if necessary
        //
    }
}
