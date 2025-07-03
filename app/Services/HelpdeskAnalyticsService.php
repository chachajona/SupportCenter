<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class HelpdeskAnalyticsService
{
    /**
     * Get dashboard metrics for helpdesk overview.
     *
     * @param  array<int>  $departmentIds
     * @return array<string, mixed>
     */
    public function getDashboardMetrics(?array $departmentIds = null): array
    {
        $baseQuery = Ticket::query();

        if ($departmentIds !== null) {
            $baseQuery->whereIn('department_id', $departmentIds);
        }

        return [
            'overview' => $this->getOverviewMetrics($baseQuery),
            'tickets_by_status' => $this->getTicketsByStatus($baseQuery),
            'tickets_by_priority' => $this->getTicketsByPriority($baseQuery),
            'resolution_metrics' => $this->getResolutionMetrics($baseQuery),
            'assignment_metrics' => $this->getAssignmentMetrics($baseQuery),
            'trend_data' => $this->getTrendData($baseQuery),
            'department_performance' => $this->getDepartmentPerformance($departmentIds),
            'agent_performance' => $this->getAgentPerformance($departmentIds),
        ];
    }

    /**
     * Get overview metrics.
     *
     * @param  Builder<Ticket>  $baseQuery
     * @return array<string, mixed>
     */
    private function getOverviewMetrics(Builder $baseQuery): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $weekAgo = Carbon::now()->subDays(7);

        // Current totals
        $totalTickets = (clone $baseQuery)->count();
        $openTickets = (clone $baseQuery)->whereHas('status', fn ($q) => $q->where('is_closed', false))->count();
        $closedTickets = (clone $baseQuery)->whereHas('status', fn ($q) => $q->where('is_closed', true))->count();

        // Overdue tickets
        $overdueTickets = (clone $baseQuery)
            ->where('due_at', '<', now())
            ->whereHas('status', fn ($q) => $q->where('is_closed', false))
            ->count();

        // Unassigned tickets
        $unassignedTickets = (clone $baseQuery)
            ->whereNull('assigned_to')
            ->whereHas('status', fn ($q) => $q->where('is_closed', false))
            ->count();

        // Today's activity
        $createdToday = (clone $baseQuery)->whereDate('created_at', $today)->count();
        $resolvedToday = (clone $baseQuery)->whereDate('resolved_at', $today)->count();

        // Week comparisons
        $createdThisWeek = (clone $baseQuery)->where('created_at', '>=', $weekAgo)->count();
        $resolvedThisWeek = (clone $baseQuery)
            ->where('resolved_at', '>=', $weekAgo)
            ->whereNotNull('resolved_at')
            ->count();

        return [
            'total_tickets' => $totalTickets,
            'open_tickets' => $openTickets,
            'closed_tickets' => $closedTickets,
            'overdue_tickets' => $overdueTickets,
            'unassigned_tickets' => $unassignedTickets,
            'created_today' => $createdToday,
            'resolved_today' => $resolvedToday,
            'created_this_week' => $createdThisWeek,
            'resolved_this_week' => $resolvedThisWeek,
            'resolution_rate' => $totalTickets > 0 ? round(($closedTickets / $totalTickets) * 100, 1) : 0,
        ];
    }

    /**
     * Get tickets grouped by status.
     *
     * @param  Builder<Ticket>  $baseQuery
     * @return array<string, mixed>
     */
    private function getTicketsByStatus(Builder $baseQuery): array
    {
        $statusData = (clone $baseQuery)
            ->select('status_id', DB::raw('count(*) as count'))
            ->with('status:id,name,color,is_closed')
            ->groupBy('status_id')
            ->get();

        return $statusData->mapWithKeys(function ($item) {
            return [
                $item->status->name => [
                    'count' => $item->count,
                    'color' => $item->status->color,
                    'is_closed' => $item->status->is_closed,
                ],
            ];
        })->toArray();
    }

    /**
     * Get tickets grouped by priority.
     *
     * @param  Builder<Ticket>  $baseQuery
     * @return array<string, mixed>
     */
    private function getTicketsByPriority(Builder $baseQuery): array
    {
        $priorityData = (clone $baseQuery)
            ->select('priority_id', DB::raw('count(*) as count'))
            ->with('priority:id,name,color,level')
            ->groupBy('priority_id')
            ->get();

        return $priorityData->mapWithKeys(function ($item) {
            return [
                $item->priority->name => [
                    'count' => $item->count,
                    'color' => $item->priority->color,
                    'level' => $item->priority->level,
                ],
            ];
        })->toArray();
    }

    /**
     * Get resolution metrics.
     *
     * @param  Builder<Ticket>  $baseQuery
     * @return array<string, mixed>
     */
    private function getResolutionMetrics(Builder $baseQuery): array
    {
        $resolvedTickets = (clone $baseQuery)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', Carbon::now()->subDays(30))
            ->get(['created_at', 'resolved_at', 'priority_id']);

        if ($resolvedTickets->isEmpty()) {
            return [
                'average_resolution_time' => 0,
                'median_resolution_time' => 0,
                'resolution_time_by_priority' => [],
                'sla_compliance' => 0,
            ];
        }

        // Calculate resolution times in hours
        $resolutionTimes = $resolvedTickets->map(function ($ticket) {
            return $ticket->created_at->diffInHours($ticket->resolved_at);
        });

        $averageResolutionTime = round($resolutionTimes->avg(), 1);
        $medianResolutionTime = round($resolutionTimes->median(), 1);

        // Resolution times by priority
        $resolutionByPriority = $resolvedTickets
            ->groupBy('priority_id')
            ->map(function ($tickets) {
                $times = $tickets->map(fn ($ticket) => $ticket->created_at->diffInHours($ticket->resolved_at));

                return round($times->avg(), 1);
            })
            ->toArray();

        // Simple SLA compliance (assuming 24h for high priority, 72h for others)
        $slaCompliant = $resolvedTickets->filter(function ($ticket) {
            $resolutionHours = $ticket->created_at->diffInHours($ticket->resolved_at);
            // Simplified SLA: High priority (level 3+) = 24h, others = 72h
            $slaHours = $ticket->priority_id >= 3 ? 24 : 72;

            return $resolutionHours <= $slaHours;
        })->count();

        $slaCompliance = round(($slaCompliant / $resolvedTickets->count()) * 100, 1);

        return [
            'average_resolution_time' => $averageResolutionTime,
            'median_resolution_time' => $medianResolutionTime,
            'resolution_time_by_priority' => $resolutionByPriority,
            'sla_compliance' => $slaCompliance,
        ];
    }

    /**
     * Get assignment metrics.
     *
     * @param  Builder<Ticket>  $baseQuery
     * @return array<string, mixed>
     */
    private function getAssignmentMetrics(Builder $baseQuery): array
    {
        $totalTickets = (clone $baseQuery)->count();
        $assignedTickets = (clone $baseQuery)->whereNotNull('assigned_to')->count();
        $unassignedTickets = $totalTickets - $assignedTickets;

        // Assignment distribution
        $assignmentData = (clone $baseQuery)
            ->select('assigned_to', DB::raw('count(*) as count'))
            ->whereNotNull('assigned_to')
            ->with('assignedTo:id,name,email')
            ->groupBy('assigned_to')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        $assignmentDistribution = $assignmentData->mapWithKeys(function ($item) {
            return [
                $item->assignedTo->name => $item->count,
            ];
        })->toArray();

        return [
            'assignment_rate' => $totalTickets > 0 ? round(($assignedTickets / $totalTickets) * 100, 1) : 0,
            'assigned_tickets' => $assignedTickets,
            'unassigned_tickets' => $unassignedTickets,
            'assignment_distribution' => $assignmentDistribution,
        ];
    }

    /**
     * Get trend data for the last 30 days.
     *
     * @param  Builder<Ticket>  $baseQuery
     * @return array<string, mixed>
     */
    private function getTrendData(Builder $baseQuery): array
    {
        $days = collect();
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $days->push([
                'date' => $date->format('Y-m-d'),
                'created' => (clone $baseQuery)->whereDate('created_at', $date)->count(),
                'resolved' => (clone $baseQuery)->whereDate('resolved_at', $date)->count(),
            ]);
        }

        return $days->toArray();
    }

    /**
     * Get department performance metrics.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<string, mixed>
     */
    private function getDepartmentPerformance(?array $departmentIds): array
    {
        $query = Department::query()
            ->withCount([
                'tickets',
                'tickets as open_tickets_count' => fn ($q) => $q->whereHas('status', fn ($sq) => $sq->where('is_closed', false)),
                'tickets as resolved_tickets_count' => fn ($q) => $q->whereHas('status', fn ($sq) => $sq->where('is_closed', true)),
            ]);

        if ($departmentIds !== null) {
            $query->whereIn('id', $departmentIds);
        }

        return $query->get(['id', 'name'])
            ->map(function ($department) {
                return [
                    'department' => $department->name,
                    'total_tickets' => $department->tickets_count,
                    'open_tickets' => $department->open_tickets_count,
                    'resolved_tickets' => $department->resolved_tickets_count,
                    'resolution_rate' => $department->tickets_count > 0
                        ? round(($department->resolved_tickets_count / $department->tickets_count) * 100, 1)
                        : 0,
                ];
            })
            ->toArray();
    }

    /**
     * Get agent performance metrics.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<string, mixed>
     */
    private function getAgentPerformance(?array $departmentIds): array
    {
        $query = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['support_agent', 'department_manager']))
            ->withCount([
                'assignedTickets as total_assigned',
                'assignedTickets as resolved_assigned' => fn ($q) => $q->whereHas('status', fn ($sq) => $sq->where('is_closed', true)),
            ]);

        if ($departmentIds !== null) {
            $query->whereIn('department_id', $departmentIds);
        }

        return $query->get(['id', 'name', 'email'])
            ->map(function ($user) {
                return [
                    'agent' => $user->name,
                    'email' => $user->email,
                    'total_assigned' => $user->total_assigned,
                    'resolved_tickets' => $user->resolved_assigned,
                    'resolution_rate' => $user->total_assigned > 0
                        ? round(($user->resolved_assigned / $user->total_assigned) * 100, 1)
                        : 0,
                ];
            })
            ->toArray();
    }

    /**
     * Get recent activity for dashboard.
     *
     * @param  array<int>|null  $departmentIds
     * @return Collection<int, array<string, mixed>>
     */
    public function getRecentActivity(?array $departmentIds = null, int $limit = 10): Collection
    {
        $query = Ticket::query()
            ->with(['createdBy:id,name', 'assignedTo:id,name', 'status:id,name,color', 'priority:id,name,color'])
            ->latest()
            ->limit($limit);

        if ($departmentIds !== null) {
            $query->whereIn('department_id', $departmentIds);
        }

        return $query->get()->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'number' => $ticket->number,
                'subject' => $ticket->subject,
                'status' => [
                    'name' => $ticket->status->name,
                    'color' => $ticket->status->color,
                ],
                'priority' => [
                    'name' => $ticket->priority->name,
                    'color' => $ticket->priority->color,
                ],
                'assigned_to' => $ticket->assignedTo?->name,
                'created_by' => $ticket->createdBy->name,
                'created_at' => $ticket->created_at->diffForHumans(),
            ];
        });
    }
}
