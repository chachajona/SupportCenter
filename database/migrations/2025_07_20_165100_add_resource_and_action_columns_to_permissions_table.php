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
        Schema::table('permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('permissions', 'resource')) {
                $table->string('resource')->nullable()->after('description');
            }
            if (!Schema::hasColumn('permissions', 'action')) {
                $table->string('action')->nullable()->after('resource');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (Schema::hasColumn('permissions', 'resource')) {
                $table->dropColumn('resource');
            }
            if (Schema::hasColumn('permissions', 'action')) {
                $table->dropColumn('action');
            }
        });
    }
};
