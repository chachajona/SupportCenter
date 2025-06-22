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
        // Only proceed if role_user table exists and needs modification
        if (Schema::hasTable('role_user')) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('role_user', 'granted_by')) {
                Schema::table('role_user', function (Blueprint $table) {
                    $table->unsignedBigInteger('granted_by')->nullable();
                });

                // Add foreign key separately to avoid conflicts
                try {
                    Schema::table('role_user', function (Blueprint $table) {
                        $table->foreign('granted_by')->references('id')->on('users')->onDelete('set null');
                    });
                } catch (\Exception $e) {
                    // Foreign key might already exist or users table might not exist in test environment
                }
            }

            if (!Schema::hasColumn('role_user', 'granted_at')) {
                Schema::table('role_user', function (Blueprint $table) {
                    $table->timestamp('granted_at')->nullable();
                });
            }

            if (!Schema::hasColumn('role_user', 'expires_at')) {
                Schema::table('role_user', function (Blueprint $table) {
                    $table->timestamp('expires_at')->nullable();
                });
            }

            if (!Schema::hasColumn('role_user', 'is_active')) {
                Schema::table('role_user', function (Blueprint $table) {
                    $table->boolean('is_active')->default(true);
                });
            }

            if (!Schema::hasColumn('role_user', 'delegation_reason')) {
                Schema::table('role_user', function (Blueprint $table) {
                    $table->text('delegation_reason')->nullable();
                });
            }

            if (!Schema::hasColumn('role_user', 'created_at')) {
                Schema::table('role_user', function (Blueprint $table) {
                    $table->timestamps();
                });
            }

            // Add indexes only if they don't exist (SQLite friendly)
            $indexQueries = [
                'CREATE INDEX IF NOT EXISTS idx_role_user_active ON role_user (user_id, is_active)',
                'CREATE INDEX IF NOT EXISTS idx_role_user_expires ON role_user (expires_at)',
                'CREATE INDEX IF NOT EXISTS idx_role_user_granted ON role_user (granted_by)'
            ];

            foreach ($indexQueries as $query) {
                try {
                    DB::statement($query);
                } catch (\Exception $e) {
                    // Index might already exist or not supported, ignore
                }
            }
        }

        // Ensure model_has_roles exists for other models if needed
        if (!Schema::hasTable('model_has_roles')) {
            try {
                Schema::create('model_has_roles', function (Blueprint $table) {
                    $table->unsignedBigInteger('role_id');
                    $table->string('model_type');
                    $table->unsignedBigInteger('model_id');

                    $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
                    $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
                });

                // Add index separately to avoid conflicts
                try {
                    DB::statement('CREATE INDEX IF NOT EXISTS model_has_roles_model_id_model_type_index ON model_has_roles (model_id, model_type)');
                } catch (\Exception $e) {
                    // Index might already exist
                }
            } catch (\Exception $e) {
                // Table might already exist or roles table might not exist
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the added columns if they exist
        if (Schema::hasTable('role_user')) {
            $columnsToRemove = ['granted_by', 'granted_at', 'expires_at', 'is_active', 'delegation_reason'];

            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('role_user', $column)) {
                    try {
                        Schema::table('role_user', function (Blueprint $table) use ($column) {
                            $table->dropColumn($column);
                        });
                    } catch (\Exception $e) {
                        // Column might be referenced by foreign key, ignore
                    }
                }
            }

            // Remove timestamps if they were added
            if (Schema::hasColumn('role_user', 'created_at') && Schema::hasColumn('role_user', 'updated_at')) {
                try {
                    Schema::table('role_user', function (Blueprint $table) {
                        $table->dropColumn(['created_at', 'updated_at']);
                    });
                } catch (\Exception $e) {
                    // Ignore if can't drop
                }
            }
        }
    }
};
