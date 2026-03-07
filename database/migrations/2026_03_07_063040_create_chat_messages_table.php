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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                  ->constrained('chat_sessions')
                  ->cascadeOnDelete();

            // "user" | "assistant" | "system"
            // system messages are used internally (e.g. tool results), not displayed to user
            $table->enum('role', ['user', 'assistant', 'system']);

            // The actual message text (supports markdown, code blocks, etc.)
            $table->longText('content');

            // ── Token tracking ────────────────────────────────────────────
            // Nullable because user messages don't consume tokens on our side
            // and we might not always get detailed breakdown from all providers.
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();

            // Computed: input_tokens + output_tokens (stored for easy aggregation)
            $table->unsignedInteger('tokens')->nullable();

            // Snapshot of which model/provider generated this specific message.
            // Important: user might switch models mid-conversation.
            $table->string('model_used', 100)->nullable();
            $table->string('provider_used', 50)->nullable();

            // Extensible JSON blob for:
            // - finish_reason (stop, length, tool_calls, content_filter)
            // - tool_calls array (if this is a tool-calling assistant message)
            // - refusal, logprobs, etc.
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Most common query: load messages for a session in order
            $table->index(['session_id', 'created_at']);

            // For counting messages per session (used to trigger summarization)
            $table->index(['session_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
