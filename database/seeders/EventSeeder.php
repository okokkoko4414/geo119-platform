<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventSeeder extends Seeder
{
    private const LOCALES = ['en', 'vi', 'th', 'id', 'ms', 'zh', 'ja', 'ko'];
    private const COUNTRIES = ['US', 'VN', 'TH', 'ID', 'MY', 'CN', 'JP', 'KR'];
    private const DEVICES = ['desktop', 'mobile', 'tablet'];
    private const BROWSERS = ['Chrome', 'Safari', 'Firefox', 'Edge'];

    public function run(): void
    {
        $now = now();
        $rows = [];

        for ($daysAgo = 29; $daysAgo >= 0; $daysAgo--) {
            $date = $now->copy()->subDays($daysAgo);
            $impressionsPerDay = random_int(500, 2000);

            for ($i = 0; $i < $impressionsPerDay; $i++) {
                $hour = random_int(0, 23);
                $minute = random_int(0, 59);
                $second = random_int(0, 59);
                $createdAt = $date->copy()->setTime($hour, $minute, $second);
                $locale = self::LOCALES[array_rand(self::LOCALES)];

                $rows[] = [
                    'event_type' => 'impression',
                    'user_id' => random_int(0, 1) ? fake()->uuid() : null,
                    'session_id' => bin2hex(random_bytes(16)),
                    'locale' => $locale,
                    'country' => self::COUNTRIES[array_rand(self::COUNTRIES)],
                    'device_type' => self::DEVICES[array_rand(self::DEVICES)],
                    'browser' => self::BROWSERS[array_rand(self::BROWSERS)],
                    'is_bot' => 'false',
                    'target_url' => 'https://geo119.com/' . $locale . '/',
                    'referrer_url' => random_int(0, 1) ? 'https://google.com/' : null,
                    'metadata' => json_encode(['page_type' => 'home']),
                    'created_at' => $createdAt->toIso8601String(),
                    'updated_at' => $createdAt->toIso8601String(),
                ];

                // ~15% of impressions get a click
                if (random_int(1, 100) <= 15) {
                    $clickTime = $createdAt->copy()->addSeconds(random_int(5, 300));
                    $rows[] = [
                        'event_type' => 'click',
                        'user_id' => $rows[count($rows) - 1]['user_id'],
                        'session_id' => $rows[count($rows) - 1]['session_id'],
                        'locale' => $locale,
                        'country' => $rows[count($rows) - 1]['country'],
                        'device_type' => $rows[count($rows) - 1]['device_type'],
                        'browser' => $rows[count($rows) - 1]['browser'],
                        'is_bot' => 'false',
                        'target_url' => 'https://geo119.com/' . $locale . '/pricing',
                        'referrer_url' => $rows[count($rows) - 1]['target_url'],
                        'metadata' => json_encode(['page_type' => 'pricing']),
                        'created_at' => $clickTime->toIso8601String(),
                        'updated_at' => $clickTime->toIso8601String(),
                    ];
                }

                // Batch insert every 500 rows
                if (count($rows) >= 500) {
                    DB::table('events')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('events')->insert($rows);
        }

        // Refresh materialized view so queries return data
        DB::statement('REFRESH MATERIALIZED VIEW event_aggregates_hourly');
    }
}
