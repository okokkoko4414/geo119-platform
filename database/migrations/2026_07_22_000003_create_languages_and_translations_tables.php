<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');

        Schema::create('languages', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->string('native_name', 100)->nullable();
            $table->smallInteger('tier')->default(2);
            $table->boolean('is_active')->default(false);
            $table->string('fallback_locale', 10)->default('en');
            $table->decimal('quality_score', 5, 4)->nullable();
            $table->decimal('baseline_score', 5, 4)->nullable();
            $table->timestampsTz();

            $table->index('tier');
            $table->index('code');
        });

        DB::statement('ALTER TABLE languages ADD CONSTRAINT languages_tier_check CHECK (tier IN (1, 2, 3))');

        Schema::create('translations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('locale', 10);
            $table->string('namespace', 50)->default('ui');
            $table->string('key', 255);
            $table->text('value');
            $table->text('source_value')->nullable();
            $table->decimal('quality_score', 5, 4)->nullable();
            $table->boolean('is_machine_translated')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestampsTz();

            $table->unique(['locale', 'namespace', 'key']);
            $table->index('locale');
            $table->index(['namespace', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
        Schema::dropIfExists('languages');
    }
};
