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
        Schema::create('files', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Nullable: files might be uploaded before being attached to a session
            $table->foreignId('session_id')
                  ->nullable()
                  ->constrained('chat_sessions')
                  ->nullOnDelete();

            // Original filename shown to user
            $table->string('filename');

            // Storage path relative to storage disk root
            $table->string('path');

            $table->string('mime_type', 100);

            // Size in bytes
            $table->unsignedBigInteger('size');

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
