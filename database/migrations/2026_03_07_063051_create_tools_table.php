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
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);

            // Machine-readable slug, matches PHP class name convention
            // e.g. "database_query" → DatabaseQueryTool::class
            $table->string('slug', 100)->unique();

            // Description sent to AI to explain what the tool does
            $table->text('description');

            // Whether this tool is available for AI to call
            $table->boolean('enabled')->default(true);

            $table->timestamps();

            $table->index('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tools');
    }
};
