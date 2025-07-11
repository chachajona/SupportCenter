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
     * WebAuthn credential registration event.
     */
    case WEBAUTHN_REGISTER = 'webauthn_register';

    /**
     * WebAuthn authentication event.
     */
    case WEBAUTHN_LOGIN = 'webauthn_login';

    /**
     * WebAuthn credential removal event.
     */
    case WEBAUTHN_REMOVE = 'webauthn_remove';

    /**
     * WebAuthn authentication failure.
     */
    case WEBAUTHN_FAILED = 'webauthn_failed';

    /**
     * Two-factor authentication enabled event.
     */
    case TWO_FACTOR_ENABLED = 'two_factor_enabled';

    /**
     * Two-factor authentication disabled event.
     */
    case TWO_FACTOR_DISABLED = 'two_factor_disabled';

    /**
     * Two-factor authentication recovery used event.
     */
    case TWO_FACTOR_RECOVERY_USED = 'two_factor_recovery_used';

    /**
     * Emergency access event.
     */
    case EMERGENCY_ACCESS = 'emergency_access';

    /**
     * Authentication success event.
     */
    case AUTH_SUCCESS = 'auth_success';

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
            self::WEBAUTHN_REGISTER => 'WebAuthn credential registration',
            self::WEBAUTHN_LOGIN => 'WebAuthn authentication',
            self::WEBAUTHN_REMOVE => 'WebAuthn credential removal',
            self::WEBAUTHN_FAILED => 'WebAuthn authentication failure',
            self::TWO_FACTOR_ENABLED => 'Two-factor authentication enabled',
            self::TWO_FACTOR_DISABLED => 'Two-factor authentication disabled',
            self::TWO_FACTOR_RECOVERY_USED => 'Two-factor authentication recovery used',
            self::EMERGENCY_ACCESS => 'Emergency access',
            self::AUTH_SUCCESS => 'Authentication success',
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
            self::AUTH_FAILURE,
            self::WEBAUTHN_FAILED => true,
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
            self::IP_BLOCKED, self::AUTH_FAILURE, self::WEBAUTHN_FAILED => 4,
            self::AUTH_ATTEMPT, self::AUTHORIZATION_EVENT, self::WEBAUTHN_LOGIN => 3,
            self::SESSION_EVENT, self::DATA_ACCESS, self::WEBAUTHN_REGISTER, self::WEBAUTHN_REMOVE => 2,
            self::ACCESS_GRANTED, self::SECURITY_CONFIG_CHANGE => 1,
            self::TEST_EVENT => 0,
        };
    }
}
