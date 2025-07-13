<?php

return [
    /*
    |--------------------------------------------------------------------------
    | IP Block Time-To-Live (TTL)
    |--------------------------------------------------------------------------
    |
    | This value determines how long (in seconds) an IP address will be
    | blocked after being flagged by the automated threat response system.
    | Default is 30 minutes (1800 seconds).
    |
    */
    'ip_block_ttl' => env('IP_BLOCK_TTL', 1800),

    /*
    |--------------------------------------------------------------------------
    | Threat Detection Thresholds
    |--------------------------------------------------------------------------
    |
    | Configure the thresholds for various threat detection mechanisms.
    |
    */
    'threat_detection' => [
        'failed_login_threshold' => env('THREAT_FAILED_LOGIN_THRESHOLD', 3),
        'suspicious_activity_threshold' => env('THREAT_SUSPICIOUS_THRESHOLD', 50),
        'critical_threat_threshold' => env('THREAT_CRITICAL_THRESHOLD', 80),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configure how security notifications are handled.
    |
    */
    'notifications' => [
        'rate_limit_window' => env('SECURITY_NOTIFICATION_RATE_LIMIT', 3600), // 1 hour
        'enable_email_alerts' => env('SECURITY_EMAIL_ALERTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Configure security audit logging behavior.
    |
    */
    'audit' => [
        'log_ip_blocks' => env('SECURITY_LOG_IP_BLOCKS', true),
        'log_threat_responses' => env('SECURITY_LOG_THREAT_RESPONSES', true),
        'retention_days' => env('SECURITY_LOG_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for security features.
    |
    */
    'cache' => [
        'blocked_ip_prefix' => 'blocked_ip:',
        'notification_rate_limit_prefix' => 'security_notification:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication Settings
    |--------------------------------------------------------------------------
    |
    | Configure the validity period (in seconds) for a completed two-factor
    | authentication challenge. Once this period elapses, users will be
    | prompted to re-authenticate. The default is three hours (10 800 seconds).
    |
    */
    'two_factor_confirmation_ttl' => env('TWO_FACTOR_CONFIRMATION_TTL', 10_800),
];
