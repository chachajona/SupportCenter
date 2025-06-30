<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->text('message');
            $table->boolean('is_internal')->default(false);
            $table->boolean('is_email')->default(false);
            $table->timestamps();

            // Performance indexes
            $table->index('ticket_id');
            $table->index('created_at');
            $table->index(['ticket_id', 'created_at']);
            $table->index(['ticket_id', 'is_internal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_responses');
    }
};
