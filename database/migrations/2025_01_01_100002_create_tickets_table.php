<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('subject', 255);
            $table->text('description');
            $table->unsignedTinyInteger('priority_id')->default(2);
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->foreignId('department_id')->constrained('departments');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Foreign key constraints for statuses and priorities
            $table->foreign('priority_id')->references('id')->on('ticket_priorities');
            $table->foreign('status_id')->references('id')->on('ticket_statuses');

            // Performance indexes
            $table->index('department_id');
            $table->index('assigned_to');
            $table->index('status_id');
            $table->index('priority_id');
            $table->index('created_at');
            $table->index('due_at');
            $table->index(['department_id', 'status_id']);
            $table->index(['assigned_to', 'status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
