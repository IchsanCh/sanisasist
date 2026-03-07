<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_id')
                  ->constrained('ai_providers')
                  ->cascadeOnDelete();

            // Human-readable display name, e.g. "Gemini 1.5 Pro"
            $table->string('name', 100);

            // Key sent to the API, e.g. "gemini-1.5-pro", "gpt-4o", "claude-3-5-sonnet-20241022"
            $table->string('model_key', 100);

            // Capability flags — used to show/hide features in UI
            $table->boolean('supports_image')->default(false);
            $table->boolean('supports_file')->default(false);
            $table->boolean('supports_tools')->default(false);
            $table->boolean('supports_streaming')->default(true);

            // Context window size in tokens (informational, used for context budgeting)
            $table->unsignedInteger('context_window')->nullable();

            // Sort order for dropdowns
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['provider_id', 'model_key']);
            $table->index(['provider_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
