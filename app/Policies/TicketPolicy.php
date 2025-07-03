<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * Policy for managing ticket access permissions.
 */
final class TicketPolicy
{
    /**
     * Determine whether the user can view any tickets.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'tickets.view_own',
            'tickets.view_department',
            'tickets.view_all',
        ]);
    }

    /**
     * Determine whether the user can view the ticket.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        // System admin and regional managers see all
        if ($user->hasPermissionTo('tickets.view_all')) {
            return true;
        }

        // Department managers see department tickets
        if ($user->hasPermissionTo('tickets.view_department')) {
            return $user->hasDepartmentAccess($ticket->department_id);
        }

        // Support agents see own tickets (assigned or created)
        if ($user->hasPermissionTo('tickets.view_own')) {
            $userId = $user->getKey();

            return $ticket->assigned_to === $userId || $ticket->created_by === $userId;
        }

        return false;
    }

    /**
     * Determine whether the user can create tickets.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('tickets.create');
    }

    /**
     * Determine whether the user can update the ticket.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->hasPermissionTo('tickets.edit_all')) {
            return true;
        }

        if ($user->hasPermissionTo('tickets.edit_department')) {
            return $user->hasDepartmentAccess($ticket->department_id);
        }

        if ($user->hasPermissionTo('tickets.edit_own')) {
            $userId = $user->getKey();

            return $ticket->assigned_to === $userId;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the ticket.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        if ($user->hasPermissionTo('tickets.delete_all')) {
            return true;
        }

        if ($user->hasPermissionTo('tickets.delete_department')) {
            return $user->hasDepartmentAccess($ticket->department_id);
        }

        if ($user->hasPermissionTo('tickets.delete_own')) {
            $userId = $user->getKey();

            return $ticket->assigned_to === $userId;
        }

        return false;
    }

    /**
     * Determine whether the user can assign the ticket.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->hasPermissionTo('tickets.assign') && $this->update($user, $ticket);
    }

    /**
     * Determine whether the user can transfer the ticket.
     */
    public function transfer(User $user, Ticket $ticket): bool
    {
        return $user->hasPermissionTo('tickets.transfer') && $this->update($user, $ticket);
    }

    /**
     * Determine whether the user can close the ticket.
     */
    public function close(User $user, Ticket $ticket): bool
    {
        return $user->hasPermissionTo('tickets.close') && $this->update($user, $ticket);
    }

    /**
     * Determine whether the user can reopen the ticket.
     */
    public function reopen(User $user, Ticket $ticket): bool
    {
        return $user->hasPermissionTo('tickets.reopen') && $this->update($user, $ticket);
    }

    public function viewInternal(User $user, Ticket $ticket): bool
    {
        // Internal responses visible to department staff and above
        return $user->hasAnyPermission([
            'tickets.view_department',
            'tickets.view_all',
        ]) && $this->view($user, $ticket);
    }

    public function addResponse(User $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }

    public function addInternalResponse(User $user, Ticket $ticket): bool
    {
        return $this->viewInternal($user, $ticket);
    }
}
