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
        Schema::create('tool_calls', function (Blueprint $table) {
            $table->id();
            // The assistant message that triggered this tool call
            $table->foreignId('message_id')
                  ->constrained('chat_messages')
                  ->cascadeOnDelete();

            $table->foreignId('tool_id')
                  ->constrained('tools')
                  ->cascadeOnDelete();

            // Raw JSON params the AI passed to the tool
            $table->json('input');

            // Raw output returned by the tool execution
            $table->json('output')->nullable();

            // Duration in milliseconds (useful for performance monitoring)
            $table->unsignedInteger('duration_ms')->nullable();

            // Whether the tool execution succeeded
            $table->boolean('success')->default(true);

            // Error message if execution failed
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index('message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_calls');
    }
};
