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
        // Check if permission_role table exists and role_has_permissions doesn't
        if (Schema::hasTable('permission_role') && !Schema::hasTable('role_has_permissions')) {
            // Rename the table to match Spatie's default convention
            Schema::rename('permission_role', 'role_has_permissions');
        } elseif (!Schema::hasTable('role_has_permissions')) {
            // Create the table if it doesn't exist at all
            $tableNames = config('permission.table_names');
            $columnNames = config('permission.column_names');
            $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
            $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

            Schema::create('role_has_permissions', function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
                $table->unsignedBigInteger($pivotPermission);
                $table->unsignedBigInteger($pivotRole);

                $table->foreign($pivotPermission)
                    ->references('id') // permission id
                    ->on($tableNames['permissions'])
                    ->onDelete('cascade');

                $table->foreign($pivotRole)
                    ->references('id') // role id
                    ->on($tableNames['roles'])
                    ->onDelete('cascade');

                $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if role_has_permissions table exists and permission_role doesn't
        if (Schema::hasTable('role_has_permissions') && !Schema::hasTable('permission_role')) {
            // Rename back to the old name
            Schema::rename('role_has_permissions', 'permission_role');
        }
    }
};
