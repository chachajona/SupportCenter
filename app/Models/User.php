<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail, WebAuthnAuthenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable, WebAuthnAuthentication;

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
     * Override the roles relationship from HasRoles trait to use direct pivot table.
     * This replaces the morphable relationship with a direct many-to-many relationship.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            config('permission.models.role'),
            config('permission.table_names.model_has_roles'),
            'user_id',
            'role_id'
        )->withPivot([
            'granted_by',
            'granted_at',
            'expires_at',
            'is_active',
            'delegation_reason',
        ])->withTimestamps();
    }

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
     * This is now an alias for the main roles relationship.
     */
    public function rolesWithPivot()
    {
        return $this->roles();
    }

    /**
     * Get active roles for this user.
     */
    public function activeRoles()
    {
        return $this->roles()
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
     * Get tickets assigned to this user.
     */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    /**
     * Get tickets created by this user.
     */
    public function createdTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'created_by');
    }

    /**
     * Check if user has department-scoped access to a resource.
     */
    public function hasDepartmentAccess(int $resourceDepartmentId): bool
    {
        if (! $this->department_id) {
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
        if (! $this->department) {
            return [];
        }

        return $this->department->getDescendantIds();
    }

    /**
     * Check if user can manage another user based on department hierarchy.
     */
    public function canManageUser(User $otherUser): bool
    {
        if (! $this->department || ! $otherUser->department) {
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
        return ! is_null($this->two_factor_confirmed_at);
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
            Log::error('Failed to decrypt two_factor_recovery_codes for user: '.$this->id.' - '.$e->getMessage());

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

    /**
     * Check if user has active emergency access.
     */
    public function hasEmergencyAccess(): bool
    {
        return $this->emergencyAccess()
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Get current active emergency access record.
     */
    public function getActiveEmergencyAccess(): ?EmergencyAccess
    {
        return $this->emergencyAccess()
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Check if user has emergency access with specific permission.
     */
    public function hasEmergencyPermission(string $permission): bool
    {
        $emergencyAccess = $this->getActiveEmergencyAccess();

        if (! $emergencyAccess) {
            return false;
        }

        $permissions = $emergencyAccess->permissions ?? [];

        return in_array($permission, $permissions) || in_array('*', $permissions);
    }

    /**
     * Override hasPermissionTo to respect is_active fields.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $permissionClass = $this->getPermissionClass();

        if (is_string($permission)) {
            $permission = $permissionClass::where('name', $permission)
                ->where('is_active', true)
                ->first();
        }

        if (! $permission || ! $permission->is_active) {
            return false;
        }

        // Check direct permissions
        if ($this->permissions()->where('permissions.is_active', true)->where('permissions.id', $permission->id)->exists()) {
            return true;
        }

        // Check permissions through active roles
        return $this->hasPermissionViaRole($permission, $guardName);
    }

    /**
     * Check if user has permission through active roles.
     */
    protected function hasPermissionViaRole($permission, $guardName = null): bool
    {
        return $this->roles()
            ->where('roles.is_active', true)
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->whereNull('role_user.expires_at')
                    ->orWhere('role_user.expires_at', '>', now());
            })
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('permissions.id', $permission->id)
                    ->where('permissions.is_active', true);
            })
            ->exists();
    }

    /**
     * Override hasAnyRole to respect is_active fields.
     */
    public function hasAnyRole($roles, ?string $guard = null): bool
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        return $this->roles()
            ->where('roles.is_active', true)
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->whereNull('role_user.expires_at')
                    ->orWhere('role_user.expires_at', '>', now());
            })
            ->whereIn('roles.name', $roles)
            ->exists();
    }

    /**
     * Override hasRole to respect is_active fields.
     */
    public function hasRole($roles, ?string $guard = null): bool
    {
        if (is_string($roles)) {
            return $this->hasAnyRole([$roles], $guard);
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if (! $this->hasAnyRole([$role], $guard)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }
}
