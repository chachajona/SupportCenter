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
        Schema::create('setup_status', function (Blueprint $table) {
            $table->id();
            $table->string('step', 50)->unique();
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('step');
            $table->index(['step', 'completed']);
        });

        // Insert initial setup steps
        DB::table('setup_status')->insert([
            ['step' => 'database_migration', 'created_at' => now(), 'updated_at' => now()],
            ['step' => 'roles_seeded', 'created_at' => now(), 'updated_at' => now()],
            ['step' => 'permissions_seeded', 'created_at' => now(), 'updated_at' => now()],
            ['step' => 'admin_created', 'created_at' => now(), 'updated_at' => now()],
            ['step' => 'setup_completed', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setup_status');
    }
};
