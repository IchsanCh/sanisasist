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
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            // Human-readable display name, e.g. "Google Gemini"
            $table->string('name', 100);

            // Machine-readable slug used in code & settings, e.g. "gemini"
            $table->string('slug', 50)->unique();

            // Base API URL, useful if provider allows custom endpoints (e.g. Azure OpenAI)
            $table->string('base_url')->nullable();

            // Whether this provider can be selected by users
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index('slug');
            $table->index('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
