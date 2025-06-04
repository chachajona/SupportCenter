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
        Schema::table('role_user', function (Blueprint $table) {
            // Add columns for temporal access if they don't exist
            if (!Schema::hasColumn('role_user', 'user_id')) {
                $table->unsignedBigInteger('user_id')->after('role_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            }

            if (!Schema::hasColumn('role_user', 'granted_by')) {
                $table->unsignedBigInteger('granted_by')->nullable()->after('user_id');
                $table->foreign('granted_by')->references('id')->on('users')->onDelete('set null');
            }

            if (!Schema::hasColumn('role_user', 'granted_at')) {
                $table->timestamp('granted_at')->nullable()->after('granted_by');
            }

            if (!Schema::hasColumn('role_user', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('granted_at');
            }

            if (!Schema::hasColumn('role_user', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('expires_at');
            }

            if (!Schema::hasColumn('role_user', 'delegation_reason')) {
                $table->text('delegation_reason')->nullable()->after('is_active');
            }

            if (!Schema::hasColumn('role_user', 'created_at')) {
                $table->timestamps();
            }

            // Add indexes
            $table->index(['user_id', 'is_active'], 'idx_role_user_active');
            $table->index('expires_at', 'idx_role_user_expires');
            $table->index('granted_by', 'idx_role_user_granted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['granted_by']);
            $table->dropColumn([
                'user_id',
                'granted_by',
                'granted_at',
                'expires_at',
                'is_active',
                'delegation_reason',
                'created_at',
                'updated_at'
            ]);
        });
    }
};
