<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add foreign key constraint to tickets table
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
        });

        // Add foreign key constraint to knowledge_categories table
        Schema::table('knowledge_categories', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
        });

        // Add foreign key constraint to knowledge_articles table
        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove foreign key constraints
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        Schema::table('knowledge_categories', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });
    }
};
