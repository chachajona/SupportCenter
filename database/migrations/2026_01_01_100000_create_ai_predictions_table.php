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
        Schema::create('ai_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('prediction_type', ['category', 'priority', 'escalation', 'resolution_time', 'assignment']);
            $table->json('predicted_value');
            $table->decimal('confidence_score', 3, 2);
            $table->json('actual_value')->nullable();
            $table->integer('feedback_score')->nullable();
            $table->string('model_version')->default('gemini-v1');
            $table->json('features_used')->nullable();
            $table->timestamps();

            $table->index(['prediction_type', 'created_at']);
            $table->index('confidence_score');
            $table->index('ticket_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_predictions');
    }
};
