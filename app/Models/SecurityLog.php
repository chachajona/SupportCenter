<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SecurityEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use InvalidArgumentException;

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

        // Laravel will automatically handle created_at and updated_at timestamps
        // No manual intervention needed
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
