<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if we're using SQLite (typically in tests)
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite doesn't support ENUM or MODIFY COLUMN, so we'll use a different approach
            // For SQLite, we'll just add a check constraint (if needed) but for now,
            // we'll rely on application-level validation
            return;
        }

        // Add new action types for ticket operations (MySQL only)
        DB::statement("
            ALTER TABLE permission_audits
            MODIFY COLUMN action ENUM(
                'granted',
                'revoked',
                'modified',
                'unauthorized_access_attempt',
                'ip_block_auto',
                'ip_unblock_manual',
                'ip_unblock_auto',
                'ticket_assigned',
                'ticket_unassigned',
                'ticket_transferred'
            ) NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if we're using SQLite (typically in tests)
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // Revert to previous enum values (MySQL only)
        DB::statement("
            ALTER TABLE permission_audits
            MODIFY COLUMN action ENUM(
                'granted',
                'revoked',
                'modified',
                'unauthorized_access_attempt',
                'ip_block_auto',
                'ip_unblock_manual',
                'ip_unblock_auto'
            ) NOT NULL
        ");
    }
};
