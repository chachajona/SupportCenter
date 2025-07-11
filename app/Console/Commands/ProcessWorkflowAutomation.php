<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AutomationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class ProcessWorkflowAutomation extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'workflow:process-automation
                          {--sla : Monitor SLA breaches only}
                          {--rules : Process scheduled rules only}
                          {--close : Auto-close stale tickets only}
                          {--reminders : Send follow-up reminders only}
                          {--reports : Generate automated reports only}';

    /**
     * The console command description.
     */
    protected $description = 'Process workflow automation tasks including SLA monitoring, scheduled rules, and automated actions';

    /**
     * Execute the console command.
     */
    public function handle(AutomationService $automationService): int
    {
        $this->info('🤖 Workflow Automation Processing');
        $this->newLine();

        $startTime = now();
        $totalResults = [];

        try {
            // Check which tasks to run
            $runSLA = $this->option('sla') || $this->shouldRunAllTasks();
            $runRules = $this->option('rules') || $this->shouldRunAllTasks();
            $runClose = $this->option('close') || $this->shouldRunAllTasks();
            $runReminders = $this->option('reminders') || $this->shouldRunAllTasks();
            $runReports = $this->option('reports') || $this->shouldRunAllTasks();

            // Monitor SLA breaches
            if ($runSLA) {
                $this->info('📊 Monitoring SLA breaches...');
                $slaResults = $automationService->monitorSLABreaches();
                $totalResults['sla'] = $slaResults;
                $this->displaySLAResults($slaResults);
                $this->newLine();
            }

            // Process scheduled rules
            if ($runRules) {
                $this->info('⚙️  Processing scheduled rules...');
                $rulesResults = $automationService->processScheduledRules();
                $totalResults['rules'] = $rulesResults;
                $this->displayRulesResults($rulesResults);
                $this->newLine();
            }

            // Auto-close stale tickets
            if ($runClose) {
                $this->info('🔒 Auto-closing stale tickets...');
                $closeResults = $automationService->autoCloseStaleTickets();
                $totalResults['close'] = $closeResults;
                $this->displayCloseResults($closeResults);
                $this->newLine();
            }

            // Send follow-up reminders
            if ($runReminders) {
                $this->info('📨 Sending follow-up reminders...');
                $reminderResults = $automationService->sendFollowUpReminders();
                $totalResults['reminders'] = $reminderResults;
                $this->displayReminderResults($reminderResults);
                $this->newLine();
            }

            // Generate automated reports
            if ($runReports) {
                $this->info('📈 Generating automated reports...');
                $reportResults = $automationService->generateAutomatedReports();
                $totalResults['reports'] = $reportResults;
                $this->displayReportResults($reportResults);
                $this->newLine();
            }

            $duration = $startTime->diffInSeconds(now());
            $this->info("✅ Workflow automation completed in {$duration} seconds");

            // Log summary
            Log::info('Workflow automation completed', [
                'duration_seconds' => $duration,
                'results' => $totalResults,
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Workflow automation failed: {$e->getMessage()}");

            Log::error('Workflow automation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Check if all tasks should run (no specific options provided).
     */
    private function shouldRunAllTasks(): bool
    {
        return ! $this->option('sla') &&
          ! $this->option('rules') &&
          ! $this->option('close') &&
          ! $this->option('reminders') &&
          ! $this->option('reports');
    }

    /**
     * Display SLA monitoring results.
     */
    private function displaySLAResults(array $results): void
    {
        $this->line("  📋 Tickets checked: {$results['tickets_checked']}");

        if ($results['breaches_detected'] > 0) {
            $this->warn("  ⚠️  SLA issues detected: {$results['breaches_detected']}");
            $this->line("  📈 Escalations triggered: {$results['escalations_triggered']}");
            $this->line("  📧 Notifications sent: {$results['notifications_sent']}");
        } else {
            $this->info('  ✅ No SLA issues detected');
        }
    }

    /**
     * Display scheduled rules results.
     */
    private function displayRulesResults(array $results): void
    {
        $this->line("  📋 Rules checked: {$results['rules_checked']}");
        $this->line("  ⚙️  Rules executed: {$results['rules_executed']}");
        $this->line("  🚀 Executions created: {$results['executions_created']}");
    }

    /**
     * Display auto-close results.
     */
    private function displayCloseResults(array $results): void
    {
        $this->line("  📋 Tickets checked: {$results['tickets_checked']}");

        if ($results['tickets_closed'] > 0) {
            $this->line("  🔒 Tickets closed: {$results['tickets_closed']}");
            $this->line("  📧 Notifications sent: {$results['notifications_sent']}");
        } else {
            $this->info('  ✅ No stale tickets found');
        }
    }

    /**
     * Display follow-up reminder results.
     */
    private function displayReminderResults(array $results): void
    {
        $this->line("  📋 Tickets checked: {$results['tickets_checked']}");

        if ($results['reminders_sent'] > 0) {
            $this->line("  📨 Reminders sent: {$results['reminders_sent']}");
        } else {
            $this->info('  ✅ No follow-up reminders needed');
        }
    }

    /**
     * Display report generation results.
     */
    private function displayReportResults(array $results): void
    {
        $this->line("  📈 Reports generated: {$results['reports_generated']}");
        $this->line("  📧 Reports sent: {$results['reports_sent']}");
    }
}
