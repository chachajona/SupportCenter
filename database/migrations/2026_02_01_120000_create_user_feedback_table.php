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
        Schema::create('user_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('category', [
                'bug_report',
                'feature_request',
                'improvement_suggestion',
                'general_feedback',
                'usability_issue',
                'performance_issue',
            ])->index();

            $table->string('subject');
            $table->text('description');

            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium')->index();

            $table->enum('status', [
                'open',
                'under_review',
                'in_progress',
                'implemented',
                'rejected',
                'duplicate',
            ])->default('open')->index();

            $table->enum('feature_area', [
                'authentication',
                'tickets',
                'dashboard',
                'knowledge_base',
                'user_management',
                'reports',
                'settings',
                'api',
                'ui_ux',
                'performance',
                'other',
            ])->nullable()->index();

            $table->json('metadata')->nullable(); // For additional data like browser info, steps to reproduce, etc.

            $table->timestamps();

            // Indexes for common queries
            $table->index(['category', 'status']);
            $table->index(['priority', 'created_at']);
            $table->index(['feature_area', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_feedback');
    }
};
