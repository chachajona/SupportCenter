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
        // For SQLite, we need to recreate the table with the new enum values
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN for enum, so we need to recreate the table
            $this->recreateTableForSQLite();
        } else {
            // For MySQL/PostgreSQL, we can alter the enum
            DB::statement("ALTER TABLE permission_audits MODIFY COLUMN action ENUM('granted', 'revoked', 'modified', 'unauthorized_access_attempt') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For SQLite, we need to recreate the table with the original enum values
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->recreateTableForSQLiteDown();
        } else {
            // For MySQL/PostgreSQL, we can alter the enum back
            DB::statement("ALTER TABLE permission_audits MODIFY COLUMN action ENUM('granted', 'revoked', 'modified') NOT NULL");
        }
    }

    /**
     * Recreate the table for SQLite with new enum values.
     */
    private function recreateTableForSQLite(): void
    {
        // Get existing data
        $existingData = DB::table('permission_audits')->get();

        // Drop the table
        Schema::drop('permission_audits');

        // Recreate with new enum values
        Schema::create('permission_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('permission_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->enum('action', ['granted', 'revoked', 'modified', 'unauthorized_access_attempt']);
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

        // Restore existing data
        foreach ($existingData as $record) {
            DB::table('permission_audits')->insert((array) $record);
        }
    }

    /**
     * Recreate the table for SQLite with original enum values.
     */
    private function recreateTableForSQLiteDown(): void
    {
        // Get existing data (excluding unauthorized_access_attempt records)
        $existingData = DB::table('permission_audits')
            ->whereIn('action', ['granted', 'revoked', 'modified'])
            ->get();

        // Drop the table
        Schema::drop('permission_audits');

        // Recreate with original enum values
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

        // Restore existing data
        foreach ($existingData as $record) {
            DB::table('permission_audits')->insert((array) $record);
        }
    }
};
