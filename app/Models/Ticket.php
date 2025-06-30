<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TicketPriority;
use App\Models\TicketResponse;
use App\Models\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $number
 * @property string $subject
 * @property string $description
 * @property int $priority_id
 * @property int $status_id
 * @property int $department_id
 * @property int|null $assigned_to
 * @property int $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon|null $due_at
 * @property \Carbon\Carbon|null $resolved_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Department $department
 * @property-read User|null $assignedTo
 * @property-read User $createdBy
 * @property-read User|null $updatedBy
 * @property-read TicketStatus $status
 * @property-read TicketPriority $priority
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketResponse> $responses
 */
final class Ticket extends Model
{
    /** @use HasFactory<\Database\Factories\TicketFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'number',
        'subject',
        'description',
        'priority_id',
        'status_id',
        'department_id',
        'assigned_to',
        'created_by',
        'updated_by',
        'due_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'priority_id' => 'integer',
        'status_id' => 'integer',
        'department_id' => 'integer',
        'assigned_to' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * Get the department that owns the ticket.
     *
     * @return BelongsTo<Department, Ticket>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user assigned to the ticket.
     *
     * @return BelongsTo<User, Ticket>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who created the ticket.
     *
     * @return BelongsTo<User, Ticket>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the ticket.
     *
     * @return BelongsTo<User, Ticket>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the status of the ticket.
     *
     * @return BelongsTo<TicketStatus, Ticket>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'status_id');
    }

    /**
     * Get the priority of the ticket.
     *
     * @return BelongsTo<TicketPriority, Ticket>
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id');
    }

    /**
     * Get all responses for the ticket.
     *
     * @return HasMany<TicketResponse>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(TicketResponse::class);
    }

    /**
     * Generate a unique ticket number.
     */
    public function generateTicketNumber(): string
    {
        return date('Y') . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Check if the ticket is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast() && !$this->status->is_closed;
    }

    /**
     * Check if the ticket is closed.
     */
    public function isClosed(): bool
    {
        return $this->status->is_closed;
    }

    /**
     * Check if the ticket is assigned.
     */
    public function isAssigned(): bool
    {
        return $this->assigned_to !== null;
    }

    /**
     * Get the ticket age in hours.
     */
    public function getAgeInHours(): float
    {
        return $this->created_at->diffInHours(now());
    }

    /**
     * Get the next available ticket number.
     */
    public static function getNextTicketNumber(): string
    {
        $latestTicket = self::latest('id')->first();
        $nextId = $latestTicket ? $latestTicket->id + 1 : 1;

        return date('Y') . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Scope to only open tickets.
     *
     * @param Builder<Ticket> $query
     * @return Builder<Ticket>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereHas('status', fn(Builder $q) => $q->where('is_closed', false));
    }

    /**
     * Scope to only closed tickets.
     *
     * @param Builder<Ticket> $query
     * @return Builder<Ticket>
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereHas('status', fn(Builder $q) => $q->where('is_closed', true));
    }

    /**
     * Scope to only overdue tickets.
     *
     * @param Builder<Ticket> $query
     * @return Builder<Ticket>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_at', '<', now())
            ->whereHas('status', fn(Builder $q) => $q->where('is_closed', false));
    }

    /**
     * Scope to only assigned tickets.
     *
     * @param Builder<Ticket> $query
     * @return Builder<Ticket>
     */
    public function scopeAssigned(Builder $query): Builder
    {
        return $query->whereNotNull('assigned_to');
    }

    /**
     * Scope to only unassigned tickets.
     *
     * @param Builder<Ticket> $query
     * @return Builder<Ticket>
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to');
    }

    /**
     * Scope tickets for a specific department.
     *
     * @param Builder<Ticket> $query
     */
    public function scopeForDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope tickets by priority.
     *
     * @param Builder<Ticket> $query
     */
    public function scopeByPriority(Builder $query, int $priorityId): Builder
    {
        return $query->where('priority_id', $priorityId);
    }

    /**
     * Scope to high priority tickets (level 3 and above).
     *
     * @param Builder<Ticket> $query
     * @return Builder<Ticket>
     */
    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->whereHas('priority', fn(Builder $q) => $q->where('level', '>=', 3));
    }

    // Boot method to auto-generate ticket number
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Ticket $ticket) {
            if (!$ticket->number) {
                // Generate a temporary number before creating
                $latest = static::latest('id')->first();
                $nextId = $latest ? $latest->id + 1 : 1;
                $ticket->number = date('Y') . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
            }
        });
    }
}
