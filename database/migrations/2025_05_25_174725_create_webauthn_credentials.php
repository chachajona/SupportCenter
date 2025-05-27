<?php

use Illuminate\Database\Schema\Blueprint;
use Laragear\WebAuthn\Models\WebAuthnCredential;

return WebAuthnCredential::migration()->with(function (Blueprint $table) {
    // Add custom columns to the WebAuthn credentials table
    // Note: 'alias' column is already included in the base migration
    // Note: 'disabled_at' column is already included in the base migration for enabling/disabling
});
