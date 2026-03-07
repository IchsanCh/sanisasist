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
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                 ->constrained()
                 ->cascadeOnDelete();

            // Which model was used — nullable because user might not have selected one yet,
            // or the model might be deleted later (set null on delete to preserve history)
            $table->foreignId('model_id')
                  ->nullable()
                  ->constrained('ai_models')
                  ->nullOnDelete();

            // Auto-generated from first message, can be renamed by user
            $table->string('title')->default('New Chat');

            // AI-generated rolling summary — updated every 10 messages
            // Stored as plain text, injected into context window as a system note
            $table->longText('summary')->nullable();

            // Extensible JSON blob for future metadata:
            // e.g. pinned, tags, system_prompt_override, phase-2 file refs
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Index for sidebar listing (newest chats first per user)
            $table->index(['user_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
