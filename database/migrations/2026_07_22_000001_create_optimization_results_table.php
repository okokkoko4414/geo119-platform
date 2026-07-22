<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('optimization_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('source_text');
            $table->text('optimized_text');
            $table->string('target_locale', 5);
            $table->string('optimization_type', 20);
            $table->decimal('before_score', 8, 4);
            $table->decimal('after_score', 8, 4);
            $table->decimal('improvement', 8, 4);
            $table->decimal('cost_cents', 12, 6)->default(0);
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->string('model', 50)->default('deepseek-chat');
            $table->integer('latency_ms')->default(0);
            $table->string('source_hash', 64);
            $table->boolean('from_cache')->default(false);
            $table->timestamp('cached_at');
            $table->timestamps();

            $table->index('source_hash');
            $table->index('target_locale');
            $table->index('optimization_type');
            $table->index('created_at');
            $table->index(['source_hash', 'target_locale', 'optimization_type'], 'idx_dedup_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('optimization_results');
    }
};
