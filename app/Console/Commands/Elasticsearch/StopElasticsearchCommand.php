<?php

declare(strict_types=1);

namespace App\Console\Commands\Elasticsearch;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StopElasticsearchCommand extends Command
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'elasticsearch:stop';

    /** @var string The console command description. */
    protected $description = 'Stop Elasticsearch service';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->warn('Stopping Elasticsearch...');

        try {
            if (! is_elasticsearch_running()) {
                $this->error('Elasticsearch service is NOT working!');

                return 1; // Exit with failure
            }

            // Java process command (Elasticsearch runs on Java)
            $processName = 'java.exe';

            // Command to terminate the Java process (adjust for specific Elasticsearch instances if needed)
            $command = "tasklist | findstr {$processName}";

            // Execute the command to check if the process is running
            exec($command, $output, $resultCode);

            // If the process is running, kill it
            $killCommand = "taskkill /F /IM {$processName}";
            exec($killCommand, $killOutput, $killResultCode);

            if ($killResultCode === 0) {
                $this->info('Elasticsearch stopped successfully.');

                return 0; // Exit with success
            }
            $this->error('Failed to stop Elasticsearch. Ensure you have the necessary permissions.');
            Log::error('Failed to stop Elasticsearch. Kill command output: ' . implode("\n", $killOutput));

            return 1; // Exit with failure

        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('An error occurred while stopping Elasticsearch: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            $this->error('An unexpected error occurred while stopping Elasticsearch.');

            return 2; // Exit with error
        }
    }
}
