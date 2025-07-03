<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketExportController extends Controller
{
    /**
     * Export tickets to CSV format
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Ticket::class);

        $filters = $request->validate([
            'department_id' => 'nullable|exists:departments,id',
            'status_id' => 'nullable|exists:ticket_statuses,id',
            'priority_id' => 'nullable|exists:ticket_priorities,id',
            'assigned_to' => 'nullable|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
        ]);

        $fileName = 'tickets_export_'.now()->format('Y_m_d_H_i_s').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');

            // Add BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Headers
            fputcsv($handle, [
                'Ticket Number',
                'Subject',
                'Status',
                'Priority',
                'Department',
                'Assigned To',
                'Created By',
                'Created At',
                'Due At',
                'Resolved At',
                'Response Count',
                'Resolution Time (Hours)',
                'Tags',
            ]);

            // Build query with filters
            $query = Ticket::with([
                'status',
                'priority',
                'department',
                'assignedTo',
                'createdBy',
                'responses',
            ]);

            // Apply filters
            if (isset($filters['department_id'])) {
                $query->where('department_id', $filters['department_id']);
            }

            if (isset($filters['status_id'])) {
                $query->where('status_id', $filters['status_id']);
            }

            if (isset($filters['priority_id'])) {
                $query->where('priority_id', $filters['priority_id']);
            }

            if (isset($filters['assigned_to'])) {
                $query->where('assigned_to', $filters['assigned_to']);
            }

            if (isset($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            if (isset($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('subject', 'like', '%'.$filters['search'].'%')
                        ->orWhere('description', 'like', '%'.$filters['search'].'%')
                        ->orWhere('number', 'like', '%'.$filters['search'].'%');
                });
            }

            // Process tickets in chunks to handle large datasets
            $query->orderBy('created_at', 'desc')
                ->chunk(100, function ($tickets) use ($handle) {
                    foreach ($tickets as $ticket) {
                        // Calculate resolution time
                        $resolutionTime = null;
                        if ($ticket->resolved_at) {
                            $resolutionTime = round(
                                $ticket->created_at->diffInHours($ticket->resolved_at),
                                1
                            );
                        }

                        // Format tags (if you have a tags relationship)
                        $tags = $ticket->tags ?? '';
                        if (is_array($tags)) {
                            $tags = implode(', ', $tags);
                        }

                        fputcsv($handle, [
                            $ticket->number,
                            $ticket->subject,
                            $ticket->status->name,
                            $ticket->priority->name,
                            $ticket->department->name,
                            $ticket->assignedTo?->name ?? 'Unassigned',
                            $ticket->createdBy->name,
                            $ticket->created_at->format('Y-m-d H:i:s'),
                            $ticket->due_at?->format('Y-m-d H:i:s') ?? '',
                            $ticket->resolved_at?->format('Y-m-d H:i:s') ?? '',
                            $ticket->responses->count(),
                            $resolutionTime ?? '',
                            $tags,
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Export filtered tickets summary report
     */
    public function exportSummary(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Ticket::class);

        $filters = $request->validate([
            'department_id' => 'nullable|exists:departments,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $fileName = 'tickets_summary_'.now()->format('Y_m_d_H_i_s').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');

            // Add BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Summary statistics
            fputcsv($handle, ['Ticket Summary Report']);
            fputcsv($handle, ['Generated:', now()->format('Y-m-d H:i:s')]);
            fputcsv($handle, []); // Empty row

            // Filters applied
            fputcsv($handle, ['Applied Filters:']);
            if (isset($filters['department_id'])) {
                $dept = \App\Models\Department::find($filters['department_id']);
                fputcsv($handle, ['Department:', $dept->name ?? 'Unknown']);
            }
            if (isset($filters['date_from'])) {
                fputcsv($handle, ['Date From:', $filters['date_from']]);
            }
            if (isset($filters['date_to'])) {
                fputcsv($handle, ['Date To:', $filters['date_to']]);
            }
            fputcsv($handle, []); // Empty row

            // Summary by Status
            fputcsv($handle, ['Summary by Status']);
            fputcsv($handle, ['Status', 'Count', 'Percentage']);

            $query = Ticket::query();
            if (isset($filters['department_id'])) {
                $query->where('department_id', $filters['department_id']);
            }
            if (isset($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }
            if (isset($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            $totalTickets = $query->count();

            $statusCounts = $query->join('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
                ->selectRaw('ticket_statuses.name as status_name, COUNT(*) as count')
                ->groupBy('ticket_statuses.id', 'ticket_statuses.name')
                ->get();

            foreach ($statusCounts as $status) {
                $percentage = $totalTickets > 0 ? round(($status->count / $totalTickets) * 100, 1) : 0;
                fputcsv($handle, [$status->status_name, $status->count, $percentage.'%']);
            }

            fputcsv($handle, []); // Empty row

            // Summary by Priority
            fputcsv($handle, ['Summary by Priority']);
            fputcsv($handle, ['Priority', 'Count', 'Percentage']);

            $priorityCounts = $query->join('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
                ->selectRaw('ticket_priorities.name as priority_name, COUNT(*) as count')
                ->groupBy('ticket_priorities.id', 'ticket_priorities.name')
                ->get();

            foreach ($priorityCounts as $priority) {
                $percentage = $totalTickets > 0 ? round(($priority->count / $totalTickets) * 100, 1) : 0;
                fputcsv($handle, [$priority->priority_name, $priority->count, $percentage.'%']);
            }

            fputcsv($handle, []); // Empty row

            // Performance Metrics
            fputcsv($handle, ['Performance Metrics']);
            fputcsv($handle, ['Metric', 'Value']);

            $avgResolutionTime = $query->whereNotNull('resolved_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours')
                ->value('avg_hours');

            $resolvedTickets = $query->whereNotNull('resolved_at')->count();
            $resolutionRate = $totalTickets > 0 ? round(($resolvedTickets / $totalTickets) * 100, 1) : 0;

            fputcsv($handle, ['Total Tickets', $totalTickets]);
            fputcsv($handle, ['Resolved Tickets', $resolvedTickets]);
            fputcsv($handle, ['Resolution Rate', $resolutionRate.'%']);
            fputcsv($handle, ['Average Resolution Time (Hours)', round($avgResolutionTime ?? 0, 1)]);

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Get export statistics for the current user
     */
    public function getExportStats(Request $request)
    {
        Gate::authorize('viewAny', Ticket::class);

        $user = $request->user();

        // Count available tickets for export based on user permissions
        $ticketCount = Ticket::when(
            ! $user->hasRole('system_administrator'),
            fn ($query) => $query->where('department_id', $user->department_id)
        )->count();

        return response()->json([
            'total_tickets' => $ticketCount,
            'max_export_limit' => 10000, // Configurable limit
            'estimated_file_size_mb' => round(($ticketCount * 0.5) / 1024, 2), // Rough estimate
            'available_formats' => ['csv'],
            'recommended_chunk_size' => min($ticketCount, 1000),
        ]);
    }
}
