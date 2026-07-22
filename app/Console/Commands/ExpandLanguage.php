<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Translation;
use App\Services\LanguageRegistry;
use App\Services\TranslationManager;
use Illuminate\Console\Command;

final class ExpandLanguage extends Command
{
    protected $signature = 'lang:expand {code : Language code (e.g. vi, ja, th)}'
        . ' {--namespace=ui : Translation namespace}'
        . ' {--retranslate : Re-translate all keys for an existing language}'
        . ' {--dry-run : Show what would be dispatched without actually doing it}';

    protected $description = 'Trigger the translation pipeline for a new (or existing) language';

    public function handle(LanguageRegistry $registry, TranslationManager $manager): int
    {
        $code = (string) $this->argument('code');
        $namespace = (string) $this->option('namespace');
        $retranslate = (bool) $this->option('retranslate');
        $dryRun = (bool) $this->option('dry-run');

        $def = $registry->getDefinition($code);
        if ($def === null) {
            $this->error("Language '{$code}' is not defined in the language registry.");

            return self::FAILURE;
        }

        $existing = $registry->findByCode($code);

        if ($existing !== null && $existing->is_active && ! $retranslate) {
            $this->info("Language '{$code}' ({$def['name']}) is already active.");
            $this->info("Use --retranslate to re-translate all keys.");

            return self::SUCCESS;
        }

        $this->info("Language: {$def['name']} ({$def['native_name']})");
        $this->info("Tier: {$def['tier']}" . ($def['tier'] === 2 ? ' (Beta badge)' : ''));
        $this->newLine();

        $sourceTranslations = Translation::locale('en')
            ->namespace($namespace)
            ->get();

        if ($sourceTranslations->isEmpty()) {
            $this->warn("No English source translations found in namespace '{$namespace}'.");
            $this->warn('Seed English translations first before expanding.');

            return self::FAILURE;
        }

        $this->info("Source keys found: {$sourceTranslations->count()}");

        if ($dryRun) {
            $this->newLine();
            $this->info('[DRY RUN] Would dispatch ' . $sourceTranslations->count() . ' translation jobs.');
            $this->table(
                ['Key', 'Source Value'],
                $sourceTranslations->take(10)->map(fn ($t) => [$t->key, \Illuminate\Support\Str::limit($t->value, 60)])
            );
            if ($sourceTranslations->count() > 10) {
                $this->line("  ... and " . ($sourceTranslations->count() - 10) . ' more');
            }

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Dispatching translation jobs...');

        $strings = $sourceTranslations->pluck('value', 'key')->toArray();
        $dispatched = $manager->expandLanguage($code, $strings, $namespace);

        $this->info("Dispatched {$dispatched} translation jobs to Horizon.");
        $this->info("Monitor with: php artisan horizon:status");

        return self::SUCCESS;
    }
}
