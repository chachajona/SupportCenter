<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Knowledge categories
        Schema::create('knowledge_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department_id', 'is_active']);
            $table->index('sort_order');
        });

        // Knowledge articles
        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('content');
            $table->text('summary')->nullable();
            $table->foreignId('category_id')->constrained('knowledge_categories')->onDelete('cascade');
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->foreignId('author_id')->constrained('users');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('view_count')->default(0);
            $table->json('tags')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // Search and filtering indexes
            // Only create fulltext index for MySQL
            if (DB::getDriverName() === 'mysql') {
                $table->fullText(['title', 'content', 'summary']);
            }
            $table->index(['status', 'is_public']);
            $table->index(['department_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index('published_at');
            $table->index('view_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_articles');
        Schema::dropIfExists('knowledge_categories');
    }
};
