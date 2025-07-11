<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $device_hash
 * @property string|null $user_agent
 * @property string|null $ip_address
 * @property Carbon|null $last_used_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \App\Models\User $user
 *
 * @method static Builder<static>|Device newModelQuery()
 * @method static Builder<static>|Device newQuery()
 * @method static Builder<static>|Device ofUser(int $userId)
 * @method static Builder<static>|Device query()
 * @method static Builder<static>|Device whereCreatedAt($value)
 * @method static Builder<static>|Device whereDeviceHash($value)
 * @method static Builder<static>|Device whereId($value)
 * @method static Builder<static>|Device whereIpAddress($value)
 * @method static Builder<static>|Device whereLastUsedAt($value)
 * @method static Builder<static>|Device whereUpdatedAt($value)
 * @method static Builder<static>|Device whereUserAgent($value)
 * @method static Builder<static>|Device whereUserId($value)
 * @method static Builder<static>|Device whereVerifiedAt($value)
 *
 * @mixin \Eloquent
 */
final class Device extends Model
{
    use HasFactory;

    protected $table = 'user_devices';

    protected $fillable = [
        'user_id',
        'device_hash',
        'user_agent',
        'ip_address',
        'last_used_at',
        'verified_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
