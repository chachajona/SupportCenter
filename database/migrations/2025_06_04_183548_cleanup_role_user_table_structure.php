<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('role_user')) {
            // Use raw SQL to handle the complex constraint dropping
            try {
                // Drop the primary key first
                DB::statement('ALTER TABLE role_user DROP PRIMARY KEY');

                // Drop the index
                DB::statement('DROP INDEX model_has_roles_model_id_model_type_index ON role_user');

                // Now drop the columns
                DB::statement('ALTER TABLE role_user DROP COLUMN model_type');
                DB::statement('ALTER TABLE role_user DROP COLUMN model_id');

                // Add the new primary key
                DB::statement('ALTER TABLE role_user ADD PRIMARY KEY (user_id, role_id)');

            } catch (\Exception $e) {
                // Log the error but continue - might be in a different state
                \Illuminate\Support\Facades\Log::warning('Error during role_user table cleanup: '.$e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('role_user')) {
            try {
                // Drop the current primary key
                DB::statement('ALTER TABLE role_user DROP PRIMARY KEY');

                // Add back morphable columns
                DB::statement('ALTER TABLE role_user ADD COLUMN model_type VARCHAR(255) NOT NULL DEFAULT "App\\\\Models\\\\User"');
                DB::statement('ALTER TABLE role_user ADD COLUMN model_id BIGINT UNSIGNED NOT NULL');

                // Update model_id to match user_id for existing records
                DB::statement('UPDATE role_user SET model_id = user_id');

                // Restore the morphable primary key
                DB::statement('ALTER TABLE role_user ADD PRIMARY KEY (role_id, model_id, model_type)');

                // Restore the index
                DB::statement('CREATE INDEX model_has_roles_model_id_model_type_index ON role_user (model_id, model_type)');

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Error during role_user table rollback: '.$e->getMessage());
            }
        }
    }
};
