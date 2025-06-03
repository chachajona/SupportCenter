<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;

class User extends Authenticatable implements WebAuthnAuthenticatable, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable, WebAuthnAuthentication;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'webauthn_enabled',
        'preferred_mfa_method',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['two_factor_enabled', 'preferred_mfa_method'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_confirmed_at' => 'datetime',
        'webauthn_enabled' => 'boolean',
    ];

    /**
     * Determine if two-factor authentication has been enabled.
     *
     * @return bool
     */
    public function getTwoFactorEnabledAttribute()
    {
        return !is_null($this->two_factor_confirmed_at);
    }

    /**
     * Get the user's two factor recovery codes.
     *
     * @return array<int, string>
     */
    public function recoveryCodes(): array
    {
        if (is_null($this->two_factor_recovery_codes)) {
            return [];
        }

        try {
            return json_decode(decrypt($this->two_factor_recovery_codes), true) ?? [];
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            Log::error('Failed to decrypt two_factor_recovery_codes for user: ' . $this->id . ' - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if user has WebAuthn credentials registered.
     */
    public function hasWebAuthnCredentials(): bool
    {
        return $this->webAuthnCredentials()->whereEnabled()->exists();
    }

    /**
     * Get the user's preferred MFA method.
     */
    public function getPreferredMfaMethodAttribute(): string
    {
        // Check if WebAuthn is enabled and has credentials
        if ($this->webauthn_enabled && $this->hasWebAuthnCredentials()) {
            return 'webauthn';
        }

        // Check if TOTP 2FA is enabled
        if ($this->two_factor_enabled) {
            return 'totp';
        }

        return 'none';
    }

    /**
     * Check if user has any MFA method enabled.
     */
    public function hasMfaEnabled(): bool
    {
        return $this->two_factor_enabled || $this->hasWebAuthnCredentials();
    }
}
