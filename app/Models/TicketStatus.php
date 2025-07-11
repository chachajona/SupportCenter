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
 * @property bool $is_closed
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Ticket> $tickets
 * @property-read int|null $tickets_count
 *
 * @method static Builder<static>|TicketStatus closed()
 * @method static Builder<static>|TicketStatus newModelQuery()
 * @method static Builder<static>|TicketStatus newQuery()
 * @method static Builder<static>|TicketStatus open()
 * @method static Builder<static>|TicketStatus ordered()
 * @method static Builder<static>|TicketStatus query()
 * @method static Builder<static>|TicketStatus whereColor($value)
 * @method static Builder<static>|TicketStatus whereCreatedAt($value)
 * @method static Builder<static>|TicketStatus whereId($value)
 * @method static Builder<static>|TicketStatus whereIsClosed($value)
 * @method static Builder<static>|TicketStatus whereName($value)
 * @method static Builder<static>|TicketStatus whereSortOrder($value)
 * @method static Builder<static>|TicketStatus whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class TicketStatus extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'color',
        'is_closed',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_closed' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get all tickets with this status.
     *
     * @return HasMany<Ticket>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'status_id');
    }

    /**
     * Scope to only open statuses.
     *
     * @param  Builder<TicketStatus>  $query
     * @return Builder<TicketStatus>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_closed', false);
    }

    /**
     * Scope to only closed statuses.
     *
     * @param  Builder<TicketStatus>  $query
     * @return Builder<TicketStatus>
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('is_closed', true);
    }

    /**
     * Scope to order by sort order.
     *
     * @param  Builder<TicketStatus>  $query
     * @return Builder<TicketStatus>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
