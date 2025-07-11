<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SecurityEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int|null $user_id
 * @property SecurityEventType $event_type
 * @property string $ip_address
 * @property string|null $user_agent
 * @property array<array-key, mixed>|null $details
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityLog whereDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityLog whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityLog whereUserId($value)
 *
 * @mixin \Eloquent
 */
class SecurityLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'event_type',
        'ip_address',
        'user_agent',
        'details',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'details' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'event_type' => SecurityEventType::class,
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The model's boot method.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::created(function (self $log): void {
            try {
                event(new \App\Events\SecurityEvent($log));
            } catch (\Throwable $e) {
                // Silently ignore broadcasting failures to avoid impacting primary flow
            }
        });
    }

    /**
     * Set the event_type attribute with validation.
     *
     * @param  string|SecurityEventType  $value
     */
    public function setEventTypeAttribute($value): void
    {
        if (is_string($value)) {
            $enumCase = SecurityEventType::tryFrom($value);
            if ($enumCase === null) {
                throw new InvalidArgumentException("Invalid security event type: {$value}");
            }
            $this->attributes['event_type'] = $value;
        } elseif ($value instanceof SecurityEventType) {
            $this->attributes['event_type'] = $value->value;
        } else {
            throw new InvalidArgumentException('Event type must be a string or SecurityEventType enum');
        }
    }

    /**
     * Get the user that owns the security log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a WebAuthn-related security event.
     */
    public static function logWebAuthnEvent(SecurityEventType $eventType, ?User $user, Request $request, array $details = []): void
    {
        self::create([
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details' => array_merge([
                'request_path' => $request->path(),
                'timestamp' => now()->toISOString(),
            ], $details),
        ]);
    }
}
