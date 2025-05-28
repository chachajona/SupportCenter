<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Security event types for logging and auditing purposes.
 *
 * These enum values represent different types of security-related events
 * that can occur within the application and need to be logged for audit trails.
 */
enum SecurityEventType: string
{
    /**
     * User access was granted after passing security checks.
     */
    case ACCESS_GRANTED = 'access_granted';

    /**
     * Access was blocked due to IP address not being in allowlist.
     */
    case IP_BLOCKED = 'ip_blocked';

    /**
     * Suspicious activity was detected and flagged.
     */
    case SUSPICIOUS_ACTIVITY = 'suspicious_activity';

    /**
     * Authentication attempt (login/logout).
     */
    case AUTH_ATTEMPT = 'auth_attempt';

    /**
     * Authentication failure (invalid credentials, etc.).
     */
    case AUTH_FAILURE = 'auth_failure';

    /**
     * Session-related security event.
     */
    case SESSION_EVENT = 'session_event';

    /**
     * Permission or authorization related event.
     */
    case AUTHORIZATION_EVENT = 'authorization_event';

    /**
     * Data access or modification event.
     */
    case DATA_ACCESS = 'data_access';

    /**
     * System security configuration change.
     */
    case SECURITY_CONFIG_CHANGE = 'security_config_change';

    /**
     * Test event for development and testing purposes.
     */
    case TEST_EVENT = 'test_event';

    /**
     * Get all available event types as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get human-readable description for the event type.
     */
    public function description(): string
    {
        return match ($this) {
            self::ACCESS_GRANTED => 'Access granted after security verification',
            self::IP_BLOCKED => 'Access blocked due to IP restrictions',
            self::SUSPICIOUS_ACTIVITY => 'Suspicious activity detected',
            self::AUTH_ATTEMPT => 'Authentication attempt',
            self::AUTH_FAILURE => 'Authentication failure',
            self::SESSION_EVENT => 'Session-related security event',
            self::AUTHORIZATION_EVENT => 'Authorization or permission event',
            self::DATA_ACCESS => 'Data access or modification',
            self::SECURITY_CONFIG_CHANGE => 'Security configuration change',
            self::TEST_EVENT => 'Test event for development',
        };
    }

    /**
     * Check if this event type indicates a security threat.
     */
    public function isThreat(): bool
    {
        return match ($this) {
            self::IP_BLOCKED,
            self::SUSPICIOUS_ACTIVITY,
            self::AUTH_FAILURE => true,
            default => false,
        };
    }

    /**
     * Get the severity level of the event (1-5, where 5 is most severe).
     */
    public function severity(): int
    {
        return match ($this) {
            self::SUSPICIOUS_ACTIVITY => 5,
            self::IP_BLOCKED, self::AUTH_FAILURE => 4,
            self::AUTH_ATTEMPT, self::AUTHORIZATION_EVENT => 3,
            self::SESSION_EVENT, self::DATA_ACCESS => 2,
            self::ACCESS_GRANTED, self::SECURITY_CONFIG_CHANGE => 1,
            self::TEST_EVENT => 0,
        };
    }
}
