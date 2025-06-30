<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $user_id
 * @property string $message
 * @property bool $is_internal
 * @property bool $is_email
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Ticket $ticket
 * @property-read User|null $user
 */
final class TicketResponse extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'is_internal',
        'is_email'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_internal' => 'boolean',
        'is_email' => 'boolean',
        'ticket_id' => 'integer',
        'user_id' => 'integer'
    ];

    /**
     * Get the ticket this response belongs to.
     *
     * @return BelongsTo<Ticket, TicketResponse>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who created this response.
     *
     * @return BelongsTo<User, TicketResponse>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to only public responses.
     *
     * @param Builder<TicketResponse> $query
     * @return Builder<TicketResponse>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_internal', false);
    }

    /**
     * Scope to only internal responses.
     *
     * @param Builder<TicketResponse> $query
     * @return Builder<TicketResponse>
     */
    public function scopeInternal(Builder $query): Builder
    {
        return $query->where('is_internal', true);
    }

    /**
     * Scope to only email responses.
     *
     * @param Builder<TicketResponse> $query
     * @return Builder<TicketResponse>
     */
    public function scopeFromEmail(Builder $query): Builder
    {
        return $query->where('is_email', true);
    }

    /**
     * Check if this is a public response.
     */
    public function isPublic(): bool
    {
        return !$this->is_internal;
    }

    /**
     * Check if this is an internal response.
     */
    public function isInternal(): bool
    {
        return $this->is_internal;
    }

    /**
     * Check if this response came from an email.
     */
    public function isFromEmail(): bool
    {
        return $this->is_email;
    }
}
