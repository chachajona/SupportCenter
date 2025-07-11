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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique()->index();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('status', ['active', 'idle', 'ended', 'escalated', 'abandoned'])->default('active');
            $table->enum('channel', ['web_chat', 'mobile_app', 'widget', 'api'])->default('web_chat');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('escalated_to_ticket_id')->nullable()->constrained('tickets')->onDelete('set null');
            $table->string('escalation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->text('summary')->nullable();
            $table->decimal('average_confidence', 3, 2)->default(0.00);
            $table->integer('total_messages')->default(0);
            $table->tinyInteger('user_satisfaction_rating')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['started_at', 'ended_at']);
            $table->index('escalated_to_ticket_id');
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->text('content');
            $table->enum('sender_type', ['user', 'bot', 'agent', 'system'])->default('user');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->json('metadata')->nullable();
            $table->json('intent')->nullable();
            $table->decimal('confidence_score', 3, 2)->nullable();
            $table->json('knowledge_articles_referenced')->nullable();
            $table->json('suggested_actions')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_type', 'created_at']);
            $table->index('confidence_score');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
