<?php

declare(strict_types=1);

namespace App\Console\Commands\Elasticsearch;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StartElasticsearchCommand extends Command
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'elasticsearch:start';

    /** @var string The console command description. */
    protected $description = 'Start Elasticsearch service';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->warn('Starting Elasticsearch...');

        try {
            if (is_elasticsearch_running()) {
                $this->error('Elasticsearch service is working!');

                return 1; // Exit with failure
            }

            // Get the path to Elasticsearch executable from configuration
            $elasticsearchPath = config('services.elasticsearch.executable_path');
            if (! file_exists($elasticsearchPath)) {
                $this->error('Elasticsearch executable not found. Check the path and try again.');
                Log::error("Elasticsearch path invalid: {$elasticsearchPath}");

                return 2;
            }

            // Command to execute Elasticsearch
            // start /MIN cmd /c: Launches Elasticsearch in a minimized and detached command window using cmd /c.
            // This ensures that the terminal used to start the Artisan command is not locked or associated with Elasticsearch
            $command = "start /MIN cmd /c {$elasticsearchPath}";

            // Use popen to start the Elasticsearch process in a non-blocking way
            $process = popen($command, 'r');
            if ($process === false) {
                $this->error('Failed to start Elasticsearch.');
                Log::error('popen() failed to start Elasticsearch.');

                return 3;
            }
            // closes the process handle that was opened with popen().
            // This releases any system resources associated with the process,
            // ensuring no memory or file handles are leaked
            pclose($process);

            // Perform a health check to verify Elasticsearch is running
            $this->info('Waiting for Elasticsearch to start...');

            $isRunning = false;
            $maxAttempts = 10; // Maximum retries
            $attempt = 0;

            while ($attempt < $maxAttempts) {
                $attempt++;
                // Check if Elasticsearch is reachable
                if (is_elasticsearch_running()) {
                    $isRunning = true;
                    break;
                }
                sleep(2); // Sleep for 2 seconds before retrying
            }

            if ($isRunning) {
                $this->info('Elasticsearch started successfully.');

                return 0;
            }
            $this->error('Failed to confirm Elasticsearch startup. Check logs or try again.');
            Log::error('Elasticsearch did not respond after maximum retries.');

            return 4;

        } catch (Exception $e) {
            Log::error('Elasticsearch starting error: ' . get_class($e) . " - {$e->getMessage()}");
            $this->error('An error occurred while starting Elasticsearch. See logs for details.');

            return 5;
        }
    }
}
