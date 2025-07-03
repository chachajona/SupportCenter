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
        Schema::create('ip_allowlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->ipAddress('ip_address')->nullable(); // Single IP address
            $table->string('cidr_range', 18)->nullable(); // CIDR notation (e.g., '192.168.1.0/24')
            $table->string('description')->nullable(); // User-friendly description
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'is_active']);
            $table->index('ip_address');

            // Note: Database constraint for ensuring at least one of ip_address or cidr_range is provided
            // can be added via a check constraint in production if needed
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_allowlists');
    }
};
