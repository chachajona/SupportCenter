<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property array<array-key, mixed> $permissions
 * @property string|null $token
 * @property string $reason
 * @property int $granted_by
 * @property \Illuminate\Support\Carbon $granted_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $remaining_time
 * @property-read \App\Models\User $grantedBy
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess expired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess recent(int $days = 30)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess unused()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess whereGrantedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess whereGrantedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess wherePermissions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess whereUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyAccess whereUserId($value)
 *
 * @mixin \Eloquent
 */
class EmergencyAccess extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'emergency_access';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'permissions',
        'reason',
        'granted_by',
        'granted_at',
        'expires_at',
        'used_at',
        'is_active',
        'token',
    ];

    /**
     * The attributes that should be guarded from mass assignment.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'granted_at',
        'used_at',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'permissions' => 'array',
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who has emergency access.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who granted the emergency access.
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Scope to active emergency access records.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('expires_at', '>', now());
    }

    /**
     * Scope to expired emergency access records.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope to unused emergency access records.
     */
    public function scopeUnused($query)
    {
        return $query->whereNull('used_at');
    }

    /**
     * Scope to recent emergency access grants within given days.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('granted_at', '>=', now()->subDays($days));
    }

    /**
     * Mark emergency access as used.
     */
    public function markAsUsed(): bool
    {
        if (! $this->is_active || $this->expires_at <= now()) {
            return false;
        }

        $this->update(['used_at' => now()]);

        return true;
    }

    /**
     * Deactivate emergency access.
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Check if emergency access is currently valid.
     */
    public function isValid(): bool
    {
        return $this->is_active && $this->expires_at > now();
    }

    /**
     * Get remaining time for emergency access.
     */
    public function getRemainingTimeAttribute(): string
    {
        if (! $this->isValid()) {
            return 'Expired';
        }

        $diff = $this->expires_at->diff(now());

        if ($diff->h > 0) {
            return $diff->format('%h hours %i minutes');
        }

        return $diff->format('%i minutes');
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($emergencyAccess) {
            if (! $emergencyAccess->granted_at) {
                $emergencyAccess->granted_at = now();
            }
            if (! isset($emergencyAccess->is_active)) {
                $emergencyAccess->is_active = true;
            }
        });
    }

    /**
     * Generate a break-glass token for emergency access
     */
    public function generateBreakGlassToken(): string
    {
        $this->token = Str::uuid();
        $this->save();

        return $this->token;
    }

    /**
     * Check if this emergency access can be used for break-glass
     */
    public function canUseForBreakGlass(): bool
    {
        return $this->is_active &&
            $this->expires_at > now() &&
            ! is_null($this->token) &&
            is_null($this->used_at);
    }

    /**
     * Mark break-glass token as used
     */
    public function markTokenUsed(): void
    {
        $this->used_at = now();
        $this->save();
    }
}
