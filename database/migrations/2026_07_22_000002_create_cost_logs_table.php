<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_logs', function (Blueprint $table) {
            $table->id();
            $table->string('operation_type', 20);
            $table->integer('input_tokens');
            $table->integer('output_tokens');
            $table->string('model', 50);
            $table->integer('latency_ms');
            $table->decimal('cost_cents', 12, 6);
            $table->string('source_text_hash', 64);
            $table->string('locale', 5);
            $table->date('log_date');
            $table->timestamps();

            $table->index('log_date');
            $table->index('operation_type');
            $table->index(['log_date', 'operation_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_logs');
    }
};
