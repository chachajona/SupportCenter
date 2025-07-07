<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kb_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('knowledge_articles')->onDelete('cascade');
            $table->json('embedding_vector');
            $table->string('content_hash')->index();
            $table->string('model_used')->default('text-embedding-004');
            $table->integer('vector_dimension')->default(768);
            $table->timestamps();

            $table->unique('article_id');
            $table->index(['content_hash', 'model_used']);
            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kb_embeddings');
    }
};
