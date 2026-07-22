<?php

declare(strict_types=1);

use App\Console\Commands\ExpandLanguage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| Register CLI commands here.
|
*/

Schedule::command(ExpandLanguage::class, ['--namespace=ui'])->dailyAt('03:00');

// B2 — Refresh analytics materialized view hourly during off-peak
Schedule::call(fn () => DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY event_aggregates_hourly'))
    ->hourlyAt(15)
    ->name('analytics.refresh-aggregates');
