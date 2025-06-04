<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'guard_name',
        'is_active',
        'hierarchy_level',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'hierarchy_level' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the users assigned to this role.
     */
    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
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
     * Get active users assigned to this role.
     */
    public function activeUsers()
    {
        return $this->users()
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->whereNull('role_user.expires_at')
                    ->orWhere('role_user.expires_at', '>', now());
            });
    }

    /**
     * Scope a query to only include active roles.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to roles of a specific hierarchy level or higher.
     */
    public function scopeHierarchyLevel($query, $level)
    {
        return $query->where('hierarchy_level', '>=', $level);
    }

    /**
     * Get roles that are hierarchically below this role.
     */
    public function getSubordinateRoles()
    {
        return static::where('hierarchy_level', '<', $this->hierarchy_level)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Check if this role can manage another role based on hierarchy.
     */
    public function canManage(Role $role): bool
    {
        return $this->hierarchy_level > $role->hierarchy_level;
    }

    /**
     * Get the display name or fallback to name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->attributes['display_name'] ?? $this->name;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($role) {
            if (empty($role->guard_name)) {
                $role->guard_name = config('auth.defaults.guard');
            }
        });
    }
}
