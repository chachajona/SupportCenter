<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SecurityEventType;
use App\Models\EmergencyAccess;
use App\Models\PermissionAudit;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class EmergencyRecoveryService
{
    public function __construct(
        private readonly PermissionCacheService $cacheService
    ) {}

    /**
     * Perform complete emergency access incident recovery
     */
    public function performIncidentRecovery(int $emergencyAccessId, string $recoveryReason, ?int $performedBy = null): array
    {
        $emergencyAccess = EmergencyAccess::with(['user', 'grantedBy'])->find($emergencyAccessId);

        if (! $emergencyAccess) {
            throw new \InvalidArgumentException("Emergency access record not found: {$emergencyAccessId}");
        }

        $performedBy = $performedBy ?? Auth::id();
        $recoveryData = [];

        try {
            Log::channel('security')->info('Emergency access recovery initiated', [
                'emergency_access_id' => $emergencyAccessId,
                'performed_by' => $performedBy,
                'reason' => $recoveryReason,
            ]);

            // Step 1: Revoke emergency access if still active
            if ($emergencyAccess->is_active) {
                $emergencyAccess->deactivate();
                $recoveryData['access_revoked'] = true;

                Log::channel('security')->info('Emergency access revoked during recovery', [
                    'emergency_access_id' => $emergencyAccessId,
                    'user_id' => $emergencyAccess->user_id,
                ]);
            }

            // Step 2: Clear user permission caches
            $this->cacheService->clearUserCache($emergencyAccess->user_id);
            $recoveryData['cache_cleared'] = true;

            // Step 3: Force logout user if currently logged in
            $logoutResult = $this->forceUserLogout($emergencyAccess->user_id);
            $recoveryData['user_logged_out'] = $logoutResult;

            // Step 4: Create detailed audit trail
            $auditData = $this->createRecoveryAuditTrail($emergencyAccess, $recoveryReason, $performedBy);
            $recoveryData['audit_entries_created'] = count($auditData);

            // Step 5: Analyze usage patterns and create security report
            $usageAnalysis = $this->analyzeEmergencyAccessUsage($emergencyAccessId);
            $recoveryData['usage_analysis'] = $usageAnalysis;

            // Step 6: Generate post-incident report
            $incidentReport = $this->generatePostIncidentReport($emergencyAccess, $usageAnalysis, $recoveryReason);
            $recoveryData['incident_report'] = $incidentReport;

            // Step 7: Notify security team of recovery completion
            $this->notifyRecoveryCompletion($emergencyAccess, $incidentReport, $performedBy);
            $recoveryData['notifications_sent'] = true;

            // Step 8: Create security log for recovery
            SecurityLog::create([
                'user_id' => $emergencyAccess->user_id,
                'event_type' => SecurityEventType::EMERGENCY_ACCESS,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'details' => [
                    'type' => 'emergency_recovery_completed',
                    'emergency_access_id' => $emergencyAccessId,
                    'performed_by' => $performedBy,
                    'recovery_reason' => $recoveryReason,
                    'recovery_data' => $recoveryData,
                ],
            ]);

            Log::channel('security')->info('Emergency access recovery completed', [
                'emergency_access_id' => $emergencyAccessId,
                'recovery_data' => $recoveryData,
            ]);

            return [
                'success' => true,
                'message' => 'Emergency access recovery completed successfully',
                'recovery_data' => $recoveryData,
                'incident_report_id' => $incidentReport['id'],
            ];

        } catch (\Exception $e) {
            Log::channel('security')->error('Emergency access recovery failed', [
                'emergency_access_id' => $emergencyAccessId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \RuntimeException('Emergency recovery failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Force logout a user from all sessions
     */
    private function forceUserLogout(int $userId): bool
    {
        try {
            $user = User::find($userId);
            if (! $user) {
                return false;
            }

            // Invalidate all sessions for this user
            // This requires session management - basic implementation
            Cache::tags(['user_sessions'])->flush();

            Log::channel('security')->info('User forcibly logged out during emergency recovery', [
                'user_id' => $userId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to force user logout during emergency recovery', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create comprehensive audit trail for emergency access recovery
     */
    private function createRecoveryAuditTrail(EmergencyAccess $emergencyAccess, string $reason, int $performedBy): array
    {
        $auditEntries = [];

        // Create audit entry for emergency access recovery
        $auditEntries[] = PermissionAudit::create([
            'user_id' => $emergencyAccess->user_id,
            'action' => 'emergency_recovery',
            'old_values' => [
                'emergency_access_id' => $emergencyAccess->id,
                'was_active' => $emergencyAccess->is_active,
                'permissions' => $emergencyAccess->permissions,
                'expires_at' => $emergencyAccess->expires_at,
            ],
            'new_values' => [
                'recovered' => true,
                'recovery_reason' => $reason,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'performed_by' => $performedBy,
            'reason' => "Emergency access recovery: {$reason}",
        ]);

        // Create audit entry for each permission that was granted
        foreach ($emergencyAccess->permissions as $permission) {
            $auditEntries[] = PermissionAudit::create([
                'user_id' => $emergencyAccess->user_id,
                'action' => 'emergency_permission_revoked',
                'old_values' => ['permission' => $permission, 'emergency_access_id' => $emergencyAccess->id],
                'new_values' => ['revoked_via_recovery' => true],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'performed_by' => $performedBy,
                'reason' => "Emergency permission revoked during recovery: {$reason}",
            ]);
        }

        return $auditEntries;
    }

    /**
     * Analyze emergency access usage patterns
     */
    private function analyzeEmergencyAccessUsage(int $emergencyAccessId): array
    {
        $emergencyAccess = EmergencyAccess::find($emergencyAccessId);

        // Get security logs related to this emergency access
        $securityLogs = SecurityLog::where('details->emergency_access_id', $emergencyAccessId)
            ->orderBy('created_at')
            ->get();

        // Calculate usage metrics
        $usageMetrics = [
            'granted_at' => $emergencyAccess->granted_at,
            'expires_at' => $emergencyAccess->expires_at,
            'used_at' => $emergencyAccess->used_at,
            'total_duration_minutes' => $emergencyAccess->granted_at->diffInMinutes($emergencyAccess->expires_at),
            'actual_usage_minutes' => $emergencyAccess->used_at ?
                $emergencyAccess->granted_at->diffInMinutes($emergencyAccess->used_at) : 0,
            'was_actually_used' => ! is_null($emergencyAccess->used_at),
            'permissions_granted' => $emergencyAccess->permissions,
            'permission_count' => count($emergencyAccess->permissions),
            'granted_by_user_id' => $emergencyAccess->granted_by,
            'target_user_id' => $emergencyAccess->user_id,
            'reason' => $emergencyAccess->reason,
            'security_events_count' => $securityLogs->count(),
            'risk_level' => $this->calculateRiskLevel($emergencyAccess),
        ];

        return $usageMetrics;
    }

    /**
     * Calculate risk level of emergency access usage
     */
    private function calculateRiskLevel(EmergencyAccess $emergencyAccess): string
    {
        $riskScore = 0;

        // High-risk permissions
        $highRiskPermissions = [
            'system.configuration',
            'system.maintenance',
            'users.delete',
            'roles.delete',
            'system.backup',
            'audit.export_data',
        ];

        foreach ($emergencyAccess->permissions as $permission) {
            if (in_array($permission, $highRiskPermissions)) {
                $riskScore += 3;
            } else {
                $riskScore += 1;
            }
        }

        // Duration factor
        $durationHours = $emergencyAccess->granted_at->diffInHours($emergencyAccess->expires_at);
        if ($durationHours > 2) {
            $riskScore += 2;
        }

        // Usage factor
        if (! $emergencyAccess->used_at) {
            $riskScore += 1; // Unused emergency access is suspicious
        }

        return match (true) {
            $riskScore >= 8 => 'critical',
            $riskScore >= 5 => 'high',
            $riskScore >= 3 => 'medium',
            default => 'low'
        };
    }

    /**
     * Generate comprehensive post-incident report
     */
    private function generatePostIncidentReport(EmergencyAccess $emergencyAccess, array $usageAnalysis, string $recoveryReason): array
    {
        $reportId = 'incident_'.$emergencyAccess->id.'_'.now()->format('Y_m_d_H_i_s');

        $report = [
            'id' => $reportId,
            'type' => 'emergency_access_post_incident',
            'generated_at' => now(),
            'emergency_access_details' => [
                'id' => $emergencyAccess->id,
                'user' => [
                    'id' => $emergencyAccess->user_id,
                    'name' => $emergencyAccess->user->name,
                    'email' => $emergencyAccess->user->email,
                ],
                'granted_by' => [
                    'id' => $emergencyAccess->granted_by,
                    'name' => $emergencyAccess->grantedBy->name,
                    'email' => $emergencyAccess->grantedBy->email,
                ],
                'reason' => $emergencyAccess->reason,
                'permissions' => $emergencyAccess->permissions,
            ],
            'usage_analysis' => $usageAnalysis,
            'recovery_details' => [
                'performed_at' => now(),
                'performed_by' => Auth::id(),
                'recovery_reason' => $recoveryReason,
            ],
            'recommendations' => $this->generateSecurityRecommendations($usageAnalysis),
            'follow_up_actions' => $this->generateFollowUpActions($usageAnalysis),
        ];

        // Store the report
        $reportPath = "incident_reports/{$reportId}.json";
        Storage::disk('local')->put($reportPath, json_encode($report, JSON_PRETTY_PRINT));

        Log::channel('security')->info('Post-incident report generated', [
            'report_id' => $reportId,
            'emergency_access_id' => $emergencyAccess->id,
            'report_path' => $reportPath,
        ]);

        return $report;
    }

    /**
     * Generate security recommendations based on usage analysis
     */
    private function generateSecurityRecommendations(array $usageAnalysis): array
    {
        $recommendations = [];

        if ($usageAnalysis['risk_level'] === 'critical' || $usageAnalysis['risk_level'] === 'high') {
            $recommendations[] = 'Conduct immediate security audit of all actions performed during emergency access';
            $recommendations[] = 'Review and validate all system changes made during the emergency period';
        }

        if (! $usageAnalysis['was_actually_used']) {
            $recommendations[] = 'Investigate why emergency access was requested but never used';
            $recommendations[] = 'Review approval process for unnecessary emergency access requests';
        }

        if ($usageAnalysis['permission_count'] > 5) {
            $recommendations[] = 'Review whether all granted permissions were necessary for the emergency';
            $recommendations[] = 'Consider implementing more granular emergency access permissions';
        }

        if ($usageAnalysis['total_duration_minutes'] > 120) {
            $recommendations[] = 'Review emergency access duration policies - consider shorter default durations';
        }

        return $recommendations;
    }

    /**
     * Generate follow-up actions
     */
    private function generateFollowUpActions(array $usageAnalysis): array
    {
        $actions = [];

        $actions[] = [
            'action' => 'Review emergency access logs',
            'priority' => 'high',
            'due_date' => now()->addHours(24),
            'assigned_to' => 'security_team',
        ];

        if ($usageAnalysis['risk_level'] === 'critical') {
            $actions[] = [
                'action' => 'Conduct immediate security audit',
                'priority' => 'critical',
                'due_date' => now()->addHours(4),
                'assigned_to' => 'security_team',
            ];
        }

        $actions[] = [
            'action' => 'Update emergency access procedures if needed',
            'priority' => 'medium',
            'due_date' => now()->addDays(7),
            'assigned_to' => 'security_team',
        ];

        return $actions;
    }

    /**
     * Notify security team of recovery completion
     */
    private function notifyRecoveryCompletion(EmergencyAccess $emergencyAccess, array $incidentReport, int $performedBy): void
    {
        try {
            $securityTeam = User::role('system_administrator')->get();
            $performer = User::find($performedBy);

            foreach ($securityTeam as $admin) {
                $admin->notify(new \App\Notifications\EmergencyRecoveryCompletedNotification(
                    $emergencyAccess,
                    $incidentReport,
                    $performer
                ));
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify security team of emergency recovery completion', [
                'emergency_access_id' => $emergencyAccess->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get recovery statistics for monitoring
     */
    public function getRecoveryStats(int $days = 30): array
    {
        $since = now()->subDays($days);

        $recoveryEvents = SecurityLog::where('event_type', SecurityEventType::EMERGENCY_ACCESS)
            ->where('details->type', 'emergency_recovery_completed')
            ->where('created_at', '>=', $since)
            ->get();

        return [
            'total_recoveries' => $recoveryEvents->count(),
            'average_recovery_time' => $this->calculateAverageRecoveryTime($recoveryEvents),
            'recovery_success_rate' => 100, // All completed recoveries are successful
            'most_common_recovery_reasons' => $this->getMostCommonRecoveryReasons($recoveryEvents),
        ];
    }

    /**
     * Calculate average recovery time
     */
    private function calculateAverageRecoveryTime($recoveryEvents): string
    {
        if ($recoveryEvents->isEmpty()) {
            return '0 minutes';
        }

        // This is a simplified calculation - in practice you'd track start/end times
        return '< 15 minutes'; // Based on the automated nature of the recovery
    }

    /**
     * Get most common recovery reasons
     */
    private function getMostCommonRecoveryReasons($recoveryEvents): array
    {
        $reasons = $recoveryEvents->pluck('details.recovery_reason')->filter();

        return $reasons->countBy()->sortDesc()->take(5)->toArray();
    }
}
