<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\SlackNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Command to send daily helpdesk summary to Slack.
 */
final class SendSlackDailySummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'helpdesk:slack-summary {--date= : Date for summary (Y-m-d format)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily helpdesk summary to Slack';

    public function __construct(
        private readonly SlackNotificationService $slackService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->option('date')
          ? Carbon::createFromFormat('Y-m-d', $this->option('date'))
          : Carbon::yesterday();

        $this->info("Generating daily summary for {$date->format('Y-m-d')}...");

        // Calculate metrics for the specified date
        $metrics = $this->calculateDailyMetrics($date);

        // Send to Slack
        $this->slackService->sendDailySummary($metrics);

        $this->info('Daily summary sent to Slack successfully!');

        // Display metrics in console
        $this->displayMetrics($metrics, $date);

        return Command::SUCCESS;
    }

    /**
     * Calculate daily metrics.
     *
     * @return array<string, mixed>
     */
    private function calculateDailyMetrics(Carbon $date): array
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        // New tickets created on this date
        $newTickets = Ticket::whereBetween('created_at', [$startOfDay, $endOfDay])->count();

        // Tickets resolved on this date
        $resolvedTickets = Ticket::whereBetween('resolved_at', [$startOfDay, $endOfDay])->count();

        // Currently open tickets
        $openTickets = Ticket::whereHas('status', function ($query) {
            $query->where('is_closed', false);
        })->count();

        // Average resolution time for tickets resolved today
        $avgResolutionTime = $this->calculateAverageResolutionTime($startOfDay, $endOfDay);

        // Overdue tickets
        $overdueTickets = Ticket::where('due_at', '<', now())
            ->whereHas('status', function ($query) {
                $query->where('is_closed', false);
            })
            ->count();

        // High priority tickets created today
        $highPriorityTickets = Ticket::whereBetween('created_at', [$startOfDay, $endOfDay])
            ->whereHas('priority', function ($query) {
                $query->where('level', '>=', 3);
            })
            ->count();

        return [
            'date' => $date->format('Y-m-d'),
            'new_tickets' => $newTickets,
            'resolved_tickets' => $resolvedTickets,
            'open_tickets' => $openTickets,
            'overdue_tickets' => $overdueTickets,
            'high_priority_tickets' => $highPriorityTickets,
            'avg_resolution_time' => $avgResolutionTime,
        ];
    }

    /**
     * Calculate average resolution time for the given period.
     */
    private function calculateAverageResolutionTime(Carbon $start, Carbon $end): float
    {
        $resolvedTickets = Ticket::whereBetween('resolved_at', [$start, $end])
            ->whereNotNull('resolved_at')
            ->get();

        if ($resolvedTickets->isEmpty()) {
            return 0.0;
        }

        $totalHours = $resolvedTickets->sum(function ($ticket) {
            return $ticket->created_at->diffInHours($ticket->resolved_at);
        });

        return round($totalHours / $resolvedTickets->count(), 1);
    }

    /**
     * Display metrics in the console.
     *
     * @param  array<string, mixed>  $metrics
     */
    private function displayMetrics(array $metrics, Carbon $date): void
    {
        $this->newLine();
        $this->info("📊 Daily Summary for {$date->format('F j, Y')}:");
        $this->line('');

        $this->line("🎫 New Tickets: {$metrics['new_tickets']}");
        $this->line("✅ Resolved Tickets: {$metrics['resolved_tickets']}");
        $this->line("📂 Open Tickets: {$metrics['open_tickets']}");
        $this->line("⏰ Overdue Tickets: {$metrics['overdue_tickets']}");
        $this->line("🚨 High Priority (Today): {$metrics['high_priority_tickets']}");
        $this->line("⏱️  Avg Resolution Time: {$metrics['avg_resolution_time']} hours");

        $this->newLine();
    }
}
