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
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements WebAuthnAuthenticatable, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable, WebAuthnAuthentication, HasRoles;

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
        'department_id',
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
     * Get the department that the user belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the departments that this user manages.
     */
    public function managedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'manager_id');
    }

    /**
     * Get the roles assigned to this user with temporal access data.
     */
    public function rolesWithPivot()
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot([
                'granted_by',
                'granted_at',
                'expires_at',
                'is_active',
                'delegation_reason'
            ])
            ->withTimestamps();
    }

    /**
     * Get active roles for this user.
     */
    public function activeRoles()
    {
        return $this->rolesWithPivot()
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->whereNull('role_user.expires_at')
                    ->orWhere('role_user.expires_at', '>', now());
            });
    }

    /**
     * Get permission audit records for this user.
     */
    public function permissionAudits(): HasMany
    {
        return $this->hasMany(PermissionAudit::class);
    }

    /**
     * Get permission audits performed by this user.
     */
    public function performedAudits(): HasMany
    {
        return $this->hasMany(PermissionAudit::class, 'performed_by');
    }

    /**
     * Get emergency access records for this user.
     */
    public function emergencyAccess(): HasMany
    {
        return $this->hasMany(EmergencyAccess::class);
    }

    /**
     * Get emergency access granted by this user.
     */
    public function grantedEmergencyAccess(): HasMany
    {
        return $this->hasMany(EmergencyAccess::class, 'granted_by');
    }

    /**
     * Check if user has department-scoped access to a resource.
     */
    public function hasDepartmentAccess(int $resourceDepartmentId): bool
    {
        if (!$this->department_id) {
            return false;
        }

        // If resource belongs to same department
        if ($this->department_id === $resourceDepartmentId) {
            return true;
        }

        // Check if user's department is a parent of resource department
        $resourceDepartment = Department::find($resourceDepartmentId);
        if ($resourceDepartment && $resourceDepartment->isDescendantOf($this->department)) {
            return true;
        }

        return false;
    }

    /**
     * Get all departments the user can access (own department and descendants).
     */
    public function getAccessibleDepartmentIds(): array
    {
        if (!$this->department) {
            return [];
        }

        return $this->department->getDescendantIds();
    }

    /**
     * Check if user can manage another user based on department hierarchy.
     */
    public function canManageUser(User $otherUser): bool
    {
        if (!$this->department || !$otherUser->department) {
            return false;
        }

        // Check if the other user's department is a descendant of this user's department
        return $otherUser->department->isDescendantOf($this->department);
    }

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
