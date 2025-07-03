<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'webauthn_enabled')) {
                $table->boolean('webauthn_enabled')->default(false)->after('two_factor_confirmed_at');
            }

            if (! Schema::hasColumn('users', 'preferred_mfa_method')) {
                $table->string('preferred_mfa_method')->default('totp')->after('webauthn_enabled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'webauthn_enabled',
                'preferred_mfa_method',
            ]);
        });
    }
};
