<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'expires_at',
    ];

    /**
     * The attributes that should be guarded from mass assignment.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'granted_by',
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
     * Mark emergency access as used.
     */
    public function markAsUsed(): bool
    {
        if (!$this->is_active || $this->expires_at <= now()) {
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
        if (!$this->isValid()) {
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
            if (!$emergencyAccess->granted_at) {
                $emergencyAccess->granted_at = now();
            }
        });
    }
}
