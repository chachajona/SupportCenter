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
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'display_name')) {
                $table->string('display_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('roles', 'description')) {
                $table->text('description')->nullable()->after('display_name');
            }
            if (!Schema::hasColumn('roles', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('description');
            }
            if (!Schema::hasColumn('roles', 'hierarchy_level')) {
                $table->integer('hierarchy_level')->default(0)->after('is_active');
            }
        });

        // Add indexes
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
        // Drop indexes first to avoid SQLite issues
        try {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropIndex('idx_roles_active');
                $table->dropIndex('idx_roles_hierarchy');
            });
        } catch (\Exception $e) {
            // Indexes might not exist, ignore
        }

        // For SQLite compatibility, we need to check the connection type
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite doesn't support dropping multiple columns at once when they have constraints
            $this->dropColumnsSafely();
        } else {
            Schema::table('roles', function (Blueprint $table) {
                $columns = ['display_name', 'description', 'is_active', 'hierarchy_level'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('roles', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    /**
     * Safely drop columns for SQLite.
     */
    private function dropColumnsSafely(): void
    {
        $columns = ['display_name', 'description', 'is_active', 'hierarchy_level'];

        foreach ($columns as $column) {
            if (Schema::hasColumn('roles', $column)) {
                try {
                    Schema::table('roles', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                } catch (\Exception $e) {
                    // If dropping individual columns fails, we might need to recreate the table
                    // For now, just log the error and continue
                }
            }
        }
    }
};
