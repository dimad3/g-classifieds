<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     * explanation: "eliseev-adverts-board\chatgpt-explanations\2024.10.07 app.Console.Kernel.docx"
     * In the commands() method:
     * $this->load(__DIR__ . '/Commands');
     * we are already automatically loads all Artisan commands from the App\Console\Commands
     * directory without needing to explicitly define each one in the protected $commands array.
     *
     * @var array
     */
    protected $commands = [
        // \App\Console\Commands\RebuildRegionsTree::class,
        // \App\Console\Commands\CreateAdvertsIndex::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('banner:expire')->daily();
        $schedule->command('advert:expire')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
