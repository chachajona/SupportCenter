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
        if (Schema::hasTable('role_user')) {
            $driver = DB::getDriverName();

            // Handle foreign key constraints based on database driver
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys=OFF');
            }

            try {
                // Backup existing data
                $existingData = DB::table('role_user')->get();

                // Drop and recreate the table with the correct structure
                Schema::drop('role_user');

                Schema::create('role_user', function (Blueprint $table) {
                    // Primary foreign keys
                    $table->unsignedBigInteger('user_id');
                    $table->unsignedBigInteger('role_id');

                    // Temporal access fields
                    $table->unsignedBigInteger('granted_by')->nullable();
                    $table->timestamp('granted_at')->nullable();
                    $table->timestamp('expires_at')->nullable();
                    $table->boolean('is_active')->default(true);
                    $table->text('delegation_reason')->nullable();

                    // Standard timestamps
                    $table->timestamps();

                    // Primary key and indexes
                    $table->primary(['user_id', 'role_id']);
                    $table->index(['user_id', 'is_active'], 'idx_role_user_active');
                    $table->index('expires_at', 'idx_role_user_expires');
                    $table->index('granted_by', 'idx_role_user_granted');
                });

                // Restore data, mapping from morphable structure to direct structure
                foreach ($existingData as $record) {
                    // Only restore User model records
                    if (isset($record->model_type) && $record->model_type === 'App\\Models\\User') {
                        DB::table('role_user')->insert([
                            'user_id' => $record->model_id ?? $record->user_id,
                            'role_id' => $record->role_id,
                            'granted_by' => $record->granted_by ?? null,
                            'granted_at' => $record->granted_at ?? null,
                            'expires_at' => $record->expires_at ?? null,
                            'is_active' => $record->is_active ?? true,
                            'delegation_reason' => $record->delegation_reason ?? null,
                            'created_at' => $record->created_at ?? now(),
                            'updated_at' => $record->updated_at ?? now(),
                        ]);
                    } elseif (!isset($record->model_type)) {
                        // Direct structure already, just copy
                        DB::table('role_user')->insert([
                            'user_id' => $record->user_id,
                            'role_id' => $record->role_id,
                            'granted_by' => $record->granted_by ?? null,
                            'granted_at' => $record->granted_at ?? null,
                            'expires_at' => $record->expires_at ?? null,
                            'is_active' => $record->is_active ?? true,
                            'delegation_reason' => $record->delegation_reason ?? null,
                            'created_at' => $record->created_at ?? now(),
                            'updated_at' => $record->updated_at ?? now(),
                        ]);
                    }
                }

                // Add foreign key constraints only for MySQL (SQLite handles them differently)
                if ($driver === 'mysql') {
                    Schema::table('role_user', function (Blueprint $table) {
                        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                        $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
                        $table->foreign('granted_by')->references('id')->on('users')->onDelete('set null');
                    });
                }

            } finally {
                // Re-enable foreign key checks based on database driver
                if ($driver === 'mysql') {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                } elseif ($driver === 'sqlite') {
                    DB::statement('PRAGMA foreign_keys=ON');
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('role_user')) {
            $driver = DB::getDriverName();

            // Handle foreign key constraints based on database driver
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys=OFF');
            }

            try {
                // Backup existing data
                $existingData = DB::table('role_user')->get();

                // Drop and recreate with morphable structure
                Schema::drop('role_user');

                Schema::create('role_user', function (Blueprint $table) {
                    $table->unsignedBigInteger('role_id');
                    $table->string('model_type');
                    $table->unsignedBigInteger('model_id');
                    $table->unsignedBigInteger('user_id');
                    $table->unsignedBigInteger('granted_by')->nullable();
                    $table->timestamp('granted_at')->nullable();
                    $table->timestamp('expires_at')->nullable();
                    $table->boolean('is_active')->default(true);
                    $table->text('delegation_reason')->nullable();
                    $table->timestamps();

                    $table->primary(['role_id', 'model_id', 'model_type']);
                    $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
                });

                // Restore data with morphable structure
                foreach ($existingData as $record) {
                    DB::table('role_user')->insert([
                        'role_id' => $record->role_id,
                        'model_type' => 'App\\Models\\User',
                        'model_id' => $record->user_id,
                        'user_id' => $record->user_id,
                        'granted_by' => $record->granted_by,
                        'granted_at' => $record->granted_at,
                        'expires_at' => $record->expires_at,
                        'is_active' => $record->is_active,
                        'delegation_reason' => $record->delegation_reason,
                        'created_at' => $record->created_at,
                        'updated_at' => $record->updated_at,
                    ]);
                }

            } finally {
                // Re-enable foreign key checks based on database driver
                if ($driver === 'mysql') {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                } elseif ($driver === 'sqlite') {
                    DB::statement('PRAGMA foreign_keys=ON');
                }
            }
        }
    }
};
