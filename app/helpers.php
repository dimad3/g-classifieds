<?php

declare(strict_types=1);

use App\Http\Router\AdvertsPath;
use App\Http\Router\PagePath;
use App\Models\Adverts\Category;
use App\Models\Page;
use App\Models\Region;
use GuzzleHttp\Client;

if (! function_exists('adverts_path')) {

    /**
     * Generates an instance of the AdvertsPath class, setting the category and region properties.
     * Purpose: Simplifies the instantiation of the AdvertsPath object with the provided category and region.
     *
     * @param  Category|null  $category  The category object or null, which will be passed to the AdvertsPath instance.
     * @param  Region|null  $region  The region object or null, which will be passed to the AdvertsPath instance.
     * @return AdvertsPath Returns fully initialized AdvertsPath object (with category and region set)
     */
    function adverts_path(?Category $category, ?Region $region): AdvertsPath
    {
        // https://laravel.com/docs/8.x/container#the-make-method
        // https://laracasts.com/discuss/channels/laravel/what-does-the-word-resolve-mean?page=1&replyId=348073

        // https://chatgpt.com/share/66eee56a-6c54-800f-ab3f-d57aefb9f1d1
        // Resolve an instance of the AdvertsPath class from Laravel's service container.
        // The app()->make() method checks if there is a binding in the service container
        // for AdvertsPath and returns an instance of it, allowing automatic dependency injection.

        // Chain the withCategory() method to set the $category property within the AdvertsPath instance.
        // This method is expected to take the $category argument and store or process it within the object.

        // Chain the withRegion() method to set the $region property within the AdvertsPath instance.
        // Similar to withCategory(), this method likely assigns the $region argument to an internal property.

        return app()->make(AdvertsPath::class)
            ->withCategory($category)  // Set $category property in the AdvertsPath instance
            ->withRegion($region);     // Set $region property in the AdvertsPath instance
    }
}

if (! function_exists('page_path')) {
    /**
     * Create PagePath Object
     * Purpose: Simplifies the instantiation of the PagePath object with the provided page.
     */
    function page_path(Page $page)
    {
        return app()->make(PagePath::class)
            ->withPage($page);
    }
}

if (! function_exists('format_price')) {
    /**
     * Format the price string into a float with two decimal places, and round the value.
     */
    function format_price(?string $value): string
    {
        // Handle cases where the value starts with a dot (e.g., ".45")
        if (strpos($value, '.') === 0) {
            $value = '0' . $value;
        }

        // Remove leading zeros (e.g., "0010.05" becomes "10.05")
        $value = ltrim($value, '0');

        // Default to "0.00" if the string becomes empty after trimming
        if (empty($value)) {
            return 0.00;
        }

        // Cast the cleaned value to a float
        $floatValue = (float) $value;

        // Round the value to two decimal places
        $roundedValue = round($floatValue, 2);

        // Format the rounded value to always have two decimal places
        return number_format($roundedValue, 2, '.', '');
    }

    if (! function_exists('is_elasticsearch_running')) {
        /**
         * Check if Elasticsearch is running.
         *
         * @param  string|null  $url  The Elasticsearch URL. Defaults to config('services.elasticsearch.url').
         * @param  bool  $logErrors  the flag to enable/disable logging.
         * @return bool True if Elasticsearch is running, false otherwise.
         */
        function is_elasticsearch_running(?string $url = null, bool $logErrors = false): bool
        {
            // Get the Elasticsearch URL from configuration if not provided
            $url = $url ?? config('services.elasticsearch.url');

            // PHP's filter_var function is used to ensure the URL is valid.
            // FILTER_VALIDATE_URL (int) - Validates whether the URL name is valid according to » RFC 2396.
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                return false; // Invalid URL
            }

            // Use Guzzle to send a request to Elasticsearch
            $client = new Client([
                'timeout' => 5,
                // 'connect_timeout' => 3, // Reduce connection timeout
                // 'retry_on_failure' => false, // Prevent Guzzle from retrying failed connections
            ]);

            try {
                $response = $client->get($url, ['timeout' => 5]);

                return $response->getStatusCode() === 200; // Elasticsearch is running if response is OK
            } catch (Exception $e) {
                if ($logErrors) {
                    // Get the file and line number from the backtrace
                    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]; // Limit to the first frame
                    $file = $trace['file'] ?? 'unknown file';
                    $line = $trace['line'] ?? 'unknown line';

                    // Log the info message with file and line number
                    Log::info(
                        'Elasticsearch is not reachable. Error: ' . get_class($e) . " - {$e->getMessage()} in {$file} on line {$line}"
                    );
                }

                return false; // Elasticsearch is not running or cannot be reached
            }
        }
    }
}
