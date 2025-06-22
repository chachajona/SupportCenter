<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add department_id to users table if it doesn't exist (without foreign key for now)
        if (!Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id')->nullable()->after('email_verified_at');
                $table->index('department_id');
            });
        }

        // Create permission_audits table if it doesn't exist
        if (!Schema::hasTable('permission_audits')) {
            Schema::create('permission_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('permission_id')->nullable();
                $table->unsignedBigInteger('role_id')->nullable();
                $table->enum('action', ['granted', 'revoked', 'modified']);
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->unsignedBigInteger('performed_by')->nullable();
                $table->text('reason')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('set null');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');
                $table->foreign('performed_by')->references('id')->on('users')->onDelete('set null');

                $table->index('user_id', 'idx_audit_user');
                $table->index('performed_by', 'idx_audit_performed_by');
                $table->index('created_at', 'idx_audit_created');
            });
        }

        // Create emergency_access table if it doesn't exist
        if (!Schema::hasTable('emergency_access')) {
            Schema::create('emergency_access', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->json('permissions');
                $table->text('reason');
                $table->unsignedBigInteger('granted_by');
                $table->timestamp('granted_at')->useCurrent();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('used_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('granted_by')->references('id')->on('users')->onDelete('cascade');

                $table->index('user_id', 'idx_emergency_user');
                $table->index('expires_at', 'idx_emergency_expires');
            });
        }

        // Add missing columns to permissions table if they don't exist
        Schema::table('permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('permissions', 'resource')) {
                $table->string('resource', 100)->nullable()->after('guard_name');
            }
            if (!Schema::hasColumn('permissions', 'action')) {
                $table->string('action', 50)->nullable()->after('resource');
            }
            if (!Schema::hasColumn('permissions', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('action');
            }
        });

        // Add indexes if they don't exist
        try {
            Schema::table('permissions', function (Blueprint $table) {
                $table->index('resource', 'idx_permissions_resource');
                $table->index('action', 'idx_permissions_action');
                $table->index('is_active', 'idx_permissions_active');
            });
        } catch (\Exception $e) {
            // Indexes might already exist, ignore
        }

        try {
            Schema::table('roles', function (Blueprint $table) {
                $table->index('is_active', 'idx_roles_active');
                $table->index('hierarchy_level', 'idx_roles_hierarchy');
            });
        } catch (\Exception $e) {
            // Indexes might already exist, ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergency_access');
        Schema::dropIfExists('permission_audits');

        // Handle SQLite limitations with dropping columns
        if (Schema::hasColumn('users', 'department_id')) {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                // For SQLite, we need to drop the index first, then the column
                try {
                    DB::statement('DROP INDEX IF EXISTS users_department_id_index');
                } catch (\Exception $e) {
                    // Index might not exist, ignore
                }
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('department_id');
            });
        }

        // Drop added columns from permissions table
        Schema::table('permissions', function (Blueprint $table) {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                // For SQLite, drop indexes first
                try {
                    DB::statement('DROP INDEX IF EXISTS idx_permissions_resource');
                    DB::statement('DROP INDEX IF EXISTS idx_permissions_action');
                    DB::statement('DROP INDEX IF EXISTS idx_permissions_active');
                } catch (\Exception $e) {
                    // Indexes might not exist, ignore
                }
            }

            $columns = ['resource', 'action', 'is_active'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('permissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
