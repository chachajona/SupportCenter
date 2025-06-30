<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TicketStatus;
use App\Models\TicketPriority;
use Illuminate\Database\Seeder;

final class HelpdeskSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTicketStatuses();
        $this->seedTicketPriorities();
    }

    private function seedTicketStatuses(): void
    {
        $statuses = [
            ['name' => 'Open', 'color' => '#3b82f6', 'is_closed' => false, 'sort_order' => 1],
            ['name' => 'In Progress', 'color' => '#f59e0b', 'is_closed' => false, 'sort_order' => 2],
            ['name' => 'Pending', 'color' => '#8b5cf6', 'is_closed' => false, 'sort_order' => 3],
            ['name' => 'Resolved', 'color' => '#10b981', 'is_closed' => true, 'sort_order' => 4],
            ['name' => 'Closed', 'color' => '#6b7280', 'is_closed' => true, 'sort_order' => 5],
        ];

        foreach ($statuses as $status) {
            TicketStatus::firstOrCreate(['name' => $status['name']], $status);
        }

        $this->command->info('Ticket statuses seeded successfully');
    }

    private function seedTicketPriorities(): void
    {
        $priorities = [
            ['name' => 'Low', 'color' => '#10b981', 'level' => 1, 'sort_order' => 1],
            ['name' => 'Medium', 'color' => '#f59e0b', 'level' => 2, 'sort_order' => 2],
            ['name' => 'High', 'color' => '#ef4444', 'level' => 3, 'sort_order' => 3],
            ['name' => 'Critical', 'color' => '#dc2626', 'level' => 4, 'sort_order' => 4],
        ];

        foreach ($priorities as $priority) {
            TicketPriority::firstOrCreate(['name' => $priority['name']], $priority);
        }

        $this->command->info('Ticket priorities seeded successfully');
    }
}
