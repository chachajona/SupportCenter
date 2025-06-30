<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_statuses', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name', 50);
            $table->string('color', 7)->default('#6B7280');
            $table->boolean('is_closed')->default(false);
            $table->tinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_statuses');
    }
};
