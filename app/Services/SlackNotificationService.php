<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for sending Slack notifications for helpdesk events.
 */
final class SlackNotificationService
{
    private readonly ?string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('services.slack.webhook_url');
    }

    /**
     * Notify about a new ticket creation.
     */
    public function notifyNewTicket(Ticket $ticket): void
    {
        if (! $this->webhookUrl) {
            Log::info('Slack webhook URL not configured, skipping notification');

            return;
        }

        $message = [
            'text' => '🎫 New Ticket Created',
            'attachments' => [
                [
                    'color' => $this->getPriorityColor($ticket->priority->level),
                    'fields' => [
                        [
                            'title' => 'Ticket #'.$ticket->number,
                            'value' => $ticket->subject,
                            'short' => false,
                        ],
                        [
                            'title' => 'Department',
                            'value' => $ticket->department->name,
                            'short' => true,
                        ],
                        [
                            'title' => 'Priority',
                            'value' => $ticket->priority->name,
                            'short' => true,
                        ],
                        [
                            'title' => 'Created By',
                            'value' => $ticket->createdBy->name,
                            'short' => true,
                        ],
                        [
                            'title' => 'Status',
                            'value' => $ticket->status->name,
                            'short' => true,
                        ],
                    ],
                    'actions' => [
                        [
                            'type' => 'button',
                            'text' => 'View Ticket',
                            'url' => url("/tickets/{$ticket->id}"),
                        ],
                    ],
                    'footer' => 'Helpdesk System',
                    'ts' => $ticket->created_at->timestamp,
                ],
            ],
        ];

        $this->sendMessage($message, 'Failed to send new ticket notification');
    }

    /**
     * Notify about ticket assignment.
     */
    public function notifyTicketAssignment(Ticket $ticket): void
    {
        if (! $this->webhookUrl) {
            return;
        }

        $message = [
            'text' => '👤 Ticket Assigned',
            'attachments' => [
                [
                    'color' => '#36a64f', // Green
                    'fields' => [
                        [
                            'title' => 'Ticket #'.$ticket->number,
                            'value' => $ticket->subject,
                            'short' => false,
                        ],
                        [
                            'title' => 'Assigned To',
                            'value' => $ticket->assignedTo?->name ?? 'Unassigned',
                            'short' => true,
                        ],
                        [
                            'title' => 'Department',
                            'value' => $ticket->department->name,
                            'short' => true,
                        ],
                    ],
                    'actions' => [
                        [
                            'type' => 'button',
                            'text' => 'View Ticket',
                            'url' => url("/tickets/{$ticket->id}"),
                        ],
                    ],
                    'footer' => 'Helpdesk System',
                    'ts' => now()->timestamp,
                ],
            ],
        ];

        $this->sendMessage($message, 'Failed to send ticket assignment notification');
    }

    /**
     * Notify about high priority tickets.
     */
    public function notifyHighPriorityTicket(Ticket $ticket): void
    {
        if (! $this->webhookUrl || $ticket->priority->level < 3) {
            return;
        }

        $message = [
            'text' => '🚨 High Priority Ticket Alert',
            'attachments' => [
                [
                    'color' => 'danger',
                    'fields' => [
                        [
                            'title' => 'Ticket #'.$ticket->number,
                            'value' => $ticket->subject,
                            'short' => false,
                        ],
                        [
                            'title' => 'Priority',
                            'value' => '⚠️ '.$ticket->priority->name,
                            'short' => true,
                        ],
                        [
                            'title' => 'Department',
                            'value' => $ticket->department->name,
                            'short' => true,
                        ],
                        [
                            'title' => 'Created By',
                            'value' => $ticket->createdBy->name,
                            'short' => true,
                        ],
                        [
                            'title' => 'Due Date',
                            'value' => $ticket->due_at?->format('M j, Y g:i A') ?? 'Not set',
                            'short' => true,
                        ],
                    ],
                    'actions' => [
                        [
                            'type' => 'button',
                            'text' => 'View Ticket',
                            'url' => url("/tickets/{$ticket->id}"),
                            'style' => 'danger',
                        ],
                    ],
                    'footer' => 'Helpdesk System - Urgent Action Required',
                    'ts' => $ticket->created_at->timestamp,
                ],
            ],
        ];

        $this->sendMessage($message, 'Failed to send high priority ticket notification');
    }

    /**
     * Notify about ticket resolution.
     */
    public function notifyTicketResolution(Ticket $ticket): void
    {
        if (! $this->webhookUrl) {
            return;
        }

        $resolutionTime = $ticket->resolved_at && $ticket->created_at
          ? $ticket->created_at->diffForHumans($ticket->resolved_at, true)
          : 'Unknown';

        $message = [
            'text' => '✅ Ticket Resolved',
            'attachments' => [
                [
                    'color' => 'good',
                    'fields' => [
                        [
                            'title' => 'Ticket #'.$ticket->number,
                            'value' => $ticket->subject,
                            'short' => false,
                        ],
                        [
                            'title' => 'Resolution Time',
                            'value' => $resolutionTime,
                            'short' => true,
                        ],
                        [
                            'title' => 'Resolved By',
                            'value' => $ticket->assignedTo?->name ?? 'Unknown',
                            'short' => true,
                        ],
                    ],
                    'footer' => 'Helpdesk System',
                    'ts' => $ticket->resolved_at?->timestamp ?? now()->timestamp,
                ],
            ],
        ];

        $this->sendMessage($message, 'Failed to send ticket resolution notification');
    }

    /**
     * Send daily summary to Slack.
     *
     * @param  array<string, mixed>  $metrics
     */
    public function sendDailySummary(array $metrics): void
    {
        if (! $this->webhookUrl) {
            return;
        }

        $message = [
            'text' => '📊 Daily Helpdesk Summary',
            'attachments' => [
                [
                    'color' => '#439FE0',
                    'fields' => [
                        [
                            'title' => 'New Tickets',
                            'value' => (string) $metrics['new_tickets'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Resolved Tickets',
                            'value' => (string) $metrics['resolved_tickets'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Open Tickets',
                            'value' => (string) $metrics['open_tickets'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Avg Resolution Time',
                            'value' => $metrics['avg_resolution_time'].' hours',
                            'short' => true,
                        ],
                    ],
                    'footer' => 'Helpdesk System - Daily Report',
                    'ts' => now()->timestamp,
                ],
            ],
        ];

        $this->sendMessage($message, 'Failed to send daily summary');
    }

    /**
     * Get priority color for Slack attachments.
     */
    private function getPriorityColor(int $level): string
    {
        return match ($level) {
            4 => '#dc2626', // Critical - Red
            3 => '#ea580c', // High - Orange
            2 => '#ca8a04', // Medium - Yellow
            1 => '#16a34a', // Low - Green
            default => '#6b7280' // Unknown - Gray
        };
    }

    /**
     * Send message to Slack with error handling.
     *
     * @param  array<string, mixed>  $message
     */
    private function sendMessage(array $message, string $errorMessage): void
    {
        try {
            $response = Http::timeout(10)
                ->retry(2, 1000)
                ->post($this->webhookUrl, $message);

            if (! $response->successful()) {
                Log::warning($errorMessage, [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error($errorMessage, [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);
        }
    }
}
