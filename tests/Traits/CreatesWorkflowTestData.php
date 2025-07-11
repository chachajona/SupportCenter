<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Department;
use App\Models\TicketPriority;
use App\Models\TicketStatus;

trait CreatesWorkflowTestData
{
    protected function createWorkflowTestData(): void
    {
        // Create ticket statuses
        TicketStatus::firstOrCreate(['name' => 'Open'], [
            'name' => 'Open',
            'color' => '#3b82f6',
            'is_closed' => false,
            'sort_order' => 1,
        ]);

        TicketStatus::firstOrCreate(['name' => 'In Progress'], [
            'name' => 'In Progress',
            'color' => '#f59e0b',
            'is_closed' => false,
            'sort_order' => 2,
        ]);

        TicketStatus::firstOrCreate(['name' => 'Resolved'], [
            'name' => 'Resolved',
            'color' => '#10b981',
            'is_closed' => true,
            'sort_order' => 3,
        ]);

        TicketStatus::firstOrCreate(['name' => 'Closed'], [
            'name' => 'Closed',
            'color' => '#6b7280',
            'is_closed' => true,
            'sort_order' => 4,
        ]);

        // Create ticket priorities
        TicketPriority::firstOrCreate(['name' => 'Low'], [
            'name' => 'Low',
            'color' => '#10b981',
            'level' => 1,
            'sort_order' => 1,
        ]);

        TicketPriority::firstOrCreate(['name' => 'Medium'], [
            'name' => 'Medium',
            'color' => '#f59e0b',
            'level' => 2,
            'sort_order' => 2,
        ]);

        TicketPriority::firstOrCreate(['name' => 'High'], [
            'name' => 'High',
            'color' => '#ef4444',
            'level' => 3,
            'sort_order' => 3,
        ]);

        TicketPriority::firstOrCreate(['name' => 'Critical'], [
            'name' => 'Critical',
            'color' => '#dc2626',
            'level' => 4,
            'sort_order' => 4,
        ]);

        // Create a default department if none exists
        if (Department::count() === 0) {
            Department::create([
                'name' => 'General Support',
                'description' => 'General support department',
                'email' => 'support@example.com',
                'is_active' => true,
            ]);
        }
    }
}
