<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $ip_address
 * @property string|null $cidr_range
 * @property string|null $description
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpAllowlist newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpAllowlist newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpAllowlist query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpAllowlist whereCidrRange($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpAllowlist whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpAllowlist whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpAllowlist whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpAllowlist whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpAllowlist whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpAllowlist whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpAllowlist whereUserId($value)
 *
 * @mixin \Eloquent
 */
class IpAllowlist extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'ip_address',
        'cidr_range',
        'description',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the IP allowlist entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
