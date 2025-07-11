<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $permission_id
 * @property int|null $role_id
 * @property string $action
 * @property array<array-key, mixed>|null $old_values
 * @property array<array-key, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int|null $performed_by
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read string $description
 * @property-read \App\Models\User|null $performedBy
 * @property-read \App\Models\Permission|null $permission
 * @property-read \App\Models\Role|null $role
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit action($action)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit byPerformer($performerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit dateRange($startDate, $endDate)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit forUser($userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit recent($days = 30)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit whereNewValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit whereOldValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit wherePerformedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit wherePermissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionAudit whereUserId($value)
 *
 * @mixin \Eloquent
 */
class PermissionAudit extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'permission_audits';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'permission_id',
        'role_id',
        'action',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'performed_by',
        'reason',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user that this audit record is for.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the permission that was modified.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * Get the role that was modified.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the user who performed the action.
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Scope to filter by action type.
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by performer.
     */
    public function scopeByPerformer($query, $performerId)
    {
        return $query->where('performed_by', $performerId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to recent audit records.
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get a human-readable description of the audit record.
     */
    public function getDescriptionAttribute(): string
    {
        $performer = $this->performedBy ? $this->performedBy->name : 'System';
        $target = $this->user ? $this->user->name : 'Unknown User';

        $action = match ($this->action) {
            'granted' => 'granted',
            'revoked' => 'revoked',
            'modified' => 'modified',
            default => $this->action,
        };

        if ($this->role) {
            return "{$performer} {$action} role '{$this->role->name}' for {$target}";
        }

        if ($this->permission) {
            return "{$performer} {$action} permission '{$this->permission->name}' for {$target}";
        }

        return "{$performer} {$action} permissions for {$target}";
    }

    /**
     * Create an audit record for permission changes.
     */
    public static function logPermissionChange(
        int $userId,
        ?int $permissionId,
        ?int $roleId,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?int $performedBy = null
    ): self {
        // Validation for audit trail integrity
        if ($userId <= 0) {
            throw new \InvalidArgumentException('User ID must be a positive integer');
        }

        if (empty(trim($action))) {
            throw new \InvalidArgumentException('Action cannot be empty');
        }

        if (! in_array($action, ['granted', 'revoked', 'modified', 'unauthorized_access_attempt'])) {
            throw new \InvalidArgumentException("Invalid action: {$action}");
        }

        if (is_null($permissionId) && is_null($roleId)) {
            throw new \InvalidArgumentException('Either permission_id or role_id must be provided');
        }

        return static::create([
            'user_id' => $userId,
            'permission_id' => $permissionId,
            'role_id' => $roleId,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'performed_by' => $performedBy ?? Auth::id(),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
