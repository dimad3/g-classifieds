<?php

declare(strict_types=1);

namespace App\Console\Commands\Elasticsearch;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StatusElasticsearchCommand extends Command
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'elasticsearch:status';

    /** @var string The console command description. */
    protected $description = 'Check if Elasticsearch is running';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->warn('Checking Elasticsearch status...');

        try {
            if (is_elasticsearch_running()) {
                $this->info('Elasticsearch is running.');

                return 0; // Exit with success
            }
            $this->error('Elasticsearch is not running or cannot be reached.');

            return 1; // Exit with failure

        } catch (\Exception $e) {
            // Log any unexpected exceptions
            Log::error('Error while checking Elasticsearch status: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            $this->error('An unexpected error occurred. Please check the logs for details.');

            return 2; // Exit with error
        }
    }
}
