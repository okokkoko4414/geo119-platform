<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Database backups
        $schedule->command('db:backup hourly')->hourly();
        $schedule->command('db:backup daily')->dailyAt('03:00');

        // Horizon snapshot
        $schedule->command('horizon:snapshot')->everyFiveMinutes();

        // Prune old event partitions (B2 data)
        $schedule->command('events:prune --days=90')->daily();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
