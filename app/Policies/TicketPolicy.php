<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use App\Models\PermissionAudit;

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
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'ticket_view', $ticket);

            return true;
        }

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
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'ticket_update', $ticket);

            return true;
        }

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
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'ticket_delete', $ticket);

            return true;
        }

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
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'ticket_assign', $ticket);

            return true;
        }

        return $user->hasPermissionTo('tickets.assign') && $this->update($user, $ticket);
    }

    /**
     * Determine whether the user can transfer the ticket.
     */
    public function transfer(User $user, Ticket $ticket): bool
    {
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'ticket_transfer', $ticket);

            return true;
        }

        return $user->hasPermissionTo('tickets.transfer') && $this->update($user, $ticket);
    }

    /**
     * Determine whether the user can close the ticket.
     */
    public function close(User $user, Ticket $ticket): bool
    {
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'ticket_close', $ticket);

            return true;
        }

        return $user->hasPermissionTo('tickets.close') && $this->update($user, $ticket);
    }

    /**
     * Determine whether the user can reopen the ticket.
     */
    public function reopen(User $user, Ticket $ticket): bool
    {
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'ticket_reopen', $ticket);

            return true;
        }

        return $user->hasPermissionTo('tickets.reopen') && $this->update($user, $ticket);
    }

    public function viewInternal(User $user, Ticket $ticket): bool
    {
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'ticket_view_internal', $ticket);

            return true;
        }

        // Internal responses visible to department staff and above with proper permissions
        return $user->hasPermissionTo('tickets.view_internal_responses') && $this->view($user, $ticket);
    }

    public function addResponse(User $user, Ticket $ticket): bool
    {
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'ticket_add_response', $ticket);

            return true;
        }

        return $this->update($user, $ticket);
    }

    public function addInternalResponse(User $user, Ticket $ticket): bool
    {
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'ticket_add_internal_response', $ticket);

            return true;
        }

        // Must have permission to create internal responses
        return $user->hasPermissionTo('tickets.create_internal_responses') && $this->viewInternal($user, $ticket);
    }

    /**
     * Audit emergency access usage.
     */
    private function auditEmergencyAccess(User $user, string $action, Ticket $ticket): void
    {
        $activeEmergencyAccess = $user->getActiveEmergencyAccess();

        // Guard clause: skip auditing if emergency access is not active
        if ($activeEmergencyAccess === null) {
            return;
        }

        PermissionAudit::create([
            'user_id' => $user->getKey(),
            'action' => 'emergency_access_used',
            'old_values' => null,
            'new_values' => [
                'action' => $action,
                'ticket_id' => $ticket->getKey(),
                'emergency_access_id' => $activeEmergencyAccess->getKey(),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'performed_by' => $user->getKey(),
            'reason' => 'Emergency access used for ticket operation',
            'created_at' => now(),
        ]);
    }
}
