<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $color
 * @property int $level
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Ticket> $tickets
 */
final class TicketPriority extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'color',
        'level',
        'sort_order'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'level' => 'integer',
        'sort_order' => 'integer'
    ];

    /**
     * Get all tickets with this priority.
     *
     * @return HasMany<Ticket>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'priority_id');
    }

    /**
     * Scope to order by sort order.
     *
     * @param Builder<TicketPriority> $query
     * @return Builder<TicketPriority>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope to filter by priority level.
     *
     * @param Builder<TicketPriority> $query
     */
    public function scopeByLevel(Builder $query, int $level): Builder
    {
        return $query->where('level', $level);
    }

    /**
     * Check if this is a critical priority (level 4).
     */
    public function isCritical(): bool
    {
        return $this->level === 4;
    }

    /**
     * Check if this is a high priority (level 3 or above).
     */
    public function isHigh(): bool
    {
        return $this->level >= 3;
    }
}
