<?php

declare(strict_types=1);

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
        // Main workflows table
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->enum('trigger_type', ['manual', 'automatic', 'schedule', 'webhook']);
            $table->json('trigger_conditions')->nullable();
            $table->json('workflow_data'); // Nodes, connections, etc.
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();

            $table->index(['trigger_type', 'is_active']);
            $table->index('created_by');
        });

        // Workflow rules for automation
        Schema::create('workflow_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->enum('entity_type', ['ticket', 'user', 'department', 'knowledge_article']);
            $table->json('conditions'); // Complex conditions array
            $table->json('actions'); // Actions to execute
            $table->json('schedule')->nullable(); // For time-based triggers
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->integer('execution_limit')->nullable(); // Maximum executions
            $table->timestamp('last_executed_at')->nullable(); // Last execution time
            $table->integer('execution_count')->default(0); // Current execution count
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['entity_type', 'is_active']);
            $table->index('priority');
            $table->index('last_executed_at');
        });

        // Workflow execution history
        Schema::create('workflow_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->nullable()->constrained('workflows');
            $table->foreignId('workflow_rule_id')->nullable()->constrained('workflow_rules');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->enum('status', ['running', 'completed', 'failed', 'cancelled']);
            $table->json('execution_data'); // Input data and context
            $table->json('execution_result')->nullable(); // Output data and results
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['status', 'started_at']);
            $table->index('workflow_id');
            $table->index('workflow_rule_id');
        });

        // Individual workflow actions
        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_execution_id')->constrained('workflow_executions');
            $table->string('action_type'); // email, assign, update, ai_process, etc.
            $table->json('action_data'); // Action-specific configuration
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'skipped']);
            $table->json('result')->nullable(); // Action execution result
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_execution_id', 'status']);
            $table->index('action_type');
        });

        // Workflow templates for common patterns
        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            $table->string('category'); // vip_support, security_incident, etc.
            $table->json('template_data'); // Pre-configured workflow structure
            $table->boolean('is_system_template')->default(false);
            $table->timestamps();

            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_actions');
        Schema::dropIfExists('workflow_executions');
        Schema::dropIfExists('workflow_templates');
        Schema::dropIfExists('workflow_rules');
        Schema::dropIfExists('workflows');
    }
};
