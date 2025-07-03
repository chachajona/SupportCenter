<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WebAuthnCredentialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laragear\WebAuthn\Models\WebAuthnCredential as BaseWebAuthnCredential;

final class WebAuthnCredential extends BaseWebAuthnCredential
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): WebAuthnCredentialFactory
    {
        return WebAuthnCredentialFactory::new();
    }
}
