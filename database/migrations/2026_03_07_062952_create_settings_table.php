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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // null = global setting, non-null = user-specific override
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained()
                  ->cascadeOnDelete();

            // e.g. "active_provider", "gemini_api_key", "streaming_enabled"
            $table->string('key', 100);

            // Store as text; cast based on 'type' column in application layer
            $table->text('value')->nullable();

            // Hint for casting and UI rendering
            $table->enum('type', ['string', 'boolean', 'integer', 'json', 'encrypted'])
                  ->default('string');

            $table->timestamps();

            // A user should have only one value per key (null user_id = global)
            $table->unique(['user_id', 'key']);
            $table->index('key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
