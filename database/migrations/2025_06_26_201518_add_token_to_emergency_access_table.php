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
        Schema::table('emergency_access', function (Blueprint $table) {
            $table->uuid('token')->after('permissions')->unique()->nullable();
            $table->index(['token', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emergency_access', function (Blueprint $table) {
            $table->dropIndex(['token', 'expires_at']);
            $table->dropColumn('token');
        });
    }
};
