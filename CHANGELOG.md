# Changelog

All notable changes to this project will be documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-06-22

### Added

- **First-time Setup Wizard**: Guided, multi-step installation flow with environment checks, database configuration, automated migrations & role/permission seeding, administrator account creation, and automatic hardening (`SetupController`, `SetupMiddleware`, React wizard).
- **Role-Based Access Control (RBAC)**: Enhanced database schema, six default hierarchical roles, 69+ granular permissions with inheritance, permission caching via Redis, comprehensive policies & middleware, and `rbac:health-check` / `rbac:warm-cache` artisan commands.
- **Admin Interface**: Backend controllers and React pages for Roles, User-Roles, Permission Matrix, Analytics, Monitoring, Audit Logs and Emergency Access, including bulk permission presets, skeleton loading states and toast notifications.
- **Security Middleware**:
    - `IdleSessionTimeout` (configurable idle logout, client-side timeout provider)
    - `IpAllowlistMiddleware` with IPv4/IPv6 & CIDR support
    - `SuspiciousActivityDetection` risk-based scoring and alert emails
    - `TwoFactorChallengeMiddleware`, `DeviceRegistrationMiddleware`, `GeoRestrictionMiddleware` and more.
- **Two-Factor Authentication Enhancements**: Custom controller fully compatible with Laravel Fortify, QR-code generation fixes (RFC-6238 compliant), password-confirmation resume flow, rate-limiter definition, recovery-code toggle.
- **WebAuthn**: Complete passkey based login & registration flow with dedicated controllers and middleware.
- **Artisan Commands**: `setup:cleanup`, `rbac:health-check`, `generate:access-report`, `warm:rbac-cache`, etc.
- **Audit & Security Logging**: `SecurityLog` and `PermissionAudit` models with indexed tables, automated log entry creation, rate-limited writes and email alerts.
- **Testing Infrastructure**: 221 passing PHPUnit tests covering setup wizard, RBAC, security middleware, authentication flows, performance benchmarks and MySQL-specific scenarios.
- **Documentation**: Extensive docs for setup, authentication, RBAC roadmap/status, security fixes, email configuration, QR-code & 2FA solutions, session timeout fix summary, etc.

### Changed

- Setup wizard now uses Inertia router for bullet-proof CSRF handling and server-side 303 redirects; removed redundant progress bar in favour of enhanced stepper component.
- `IdleSessionTimeout` initialises `last_activity_time` automatically; authentication controllers set timestamp post-login/2FA/WebAuthn.
- Hardened `SetupMiddleware` to skip during tests, rely on lock-file for fast state detection, and guard cache rebuilds when `app()->environment('testing')`.
- Refactored `RolePermissionSeeder` to include wildcard permissions for `system_administrator` and explicit inheritance logic.

### Fixed

- **QR Code Compatibility**: Decrypted secrets, switched to Fortify provider, added SHA-1/digits/period params, high-contrast SVG rendering, comprehensive error handling.
- **Session Timeout Bug**: Immediate logout after login resolved by proper `last_activity_time` handling across all auth controllers and middleware.
- **Setup Security Issues**: Blocked duplicate admin creation & database reconfiguration, idempotent migration/seed endpoints, step validation & CSRF 419 errors eliminated.
- **CIDR Validation**: Robust IPv4/IPv6 parsing, mask range checks, safer subnet comparison in `IpAllowlistMiddleware`.
- **SuspiciousActivityAlert** formatting and null-safe array access; HTML mails render correctly.
- **Temporal Permission Expiry Test** and other flaky PHPUnit suites – full test suite now green.

### Security

- Read-only `.env` and `setup.lock` after successful installation.
- Rate-limited security logging (5-minute window) reducing DB writes by ~95 %.
- Session regeneration after 2FA success to prevent fixation; CSRF, throttle & validation guards across setup and auth endpoints.

### Performance

- Permission checks average < 10 ms with Redis caching.
- Reduced security-log writes significantly via caching; optimised DB indexes across new tables.

### Removed

- Legacy progress bar in setup wizard (superseded by stepper UI).

### Migration Notes

1. Requires **PHP ≥ 8.2** and **Laravel 12.x**.
2. New installs: visit `/setup` and follow wizard.
3. Existing databases: run `php artisan migrate --force && php artisan db:seed --class=RolePermissionSeeder` then `php artisan rbac:warm-cache`.
4. Ensure Redis is available for optimal caching; fallback to database/array cache supported but slower.

## [1.1.0] - 2025-06-23

### Added

- **Temporal Access Workflows**: Time-bound role assignments with automatic expiry via `TemporalAccessService::cleanupExpiredPermissions()`, dual-path grant/request flow, full approval UI, and new permissions (`roles.assign_temporal`, `roles.revoke_temporal`, `roles.request_temporal`, `roles.approve_temporal`, `roles.deny_temporal`).
- **TemporalAccessController**: Endpoints for listing, requesting, approving and denying temporal access (`/admin/temporal`, `/admin/users/{user}/temporal-access`, etc.).
- **Frontend Management UI**: `TemporalAccessForm`, `TemporalAccessRequests` component, new Admin sidebar link and dashboard list with approve/deny dialogs.

### Changed

- **UserRoleController**: Added `approveTemporal` and `denyTemporal` methods and extended audit logging.
- **RolePermissionSeeder**: Seeded new temporal permissions.
- **Routes**: Added user-level and admin-level temporal access routes secured with new permissions.

### Fixed

- TypeScript type safety improvements in new components (removed `any`, added `ErrorsObject`, etc.).
- ESLint clean-up across new frontend code.

### Migration Notes

1. Re-run `php artisan db:seed --class=RolePermissionSeeder` to populate temporal access permissions.
2. Deploy new migrations and clear permission cache (`php artisan rbac:warm-cache`).

[1.0.0]: https://github.com/your-org/support-center/releases/tag/v1.0.0
[1.1.0]: https://github.com/your-org/support-center/releases/tag/v1.1.0

## [1.2.0] - 2025-06-26

### Added

- **Real-Time Security Monitoring Dashboard**: Live WebSocket event stream via `SecurityEvent` broadcast on the `security-events` channel and new React page `admin/security/index.tsx` for real-time threat visibility.
- **Automated Threat Response Hooks**: `ThreatResponseService` with `ThreatResponseListener` automatically blocks offending IPs in cache and dispatches `SuspiciousActivityAlert` emails.
- **Emergency Break-Glass Flow**: One-time break-glass tokens with 10-minute expiry, migration `add_token_to_emergency_access_table.php`, `EmergencyAccessService::generateBreakGlass()`, controller endpoints (`/admin/emergency/break-glass`, `/break-glass`) and React page `resources/js/pages/auth/break-glass.tsx`.
- **Temporal Delegation UI**: `temporal-delegation-dialog.tsx` integrated into user role management for granting time-bound roles with live expiry summaries and revoke controls.
- **Advanced Security Middleware Enhancements**: Completed `GeoRestrictionMiddleware` and `DeviceRegistrationMiddleware`; registered missing `role` middleware alias in `bootstrap/app.php`.
- **CI: MySQL Matrix Enablement**: Updated `SetupSystemMySQLAdvancedTest` and helper scripts to support multi-database CI runs.
- **New Test Suites**: `GeoRestrictionTest`, `DeviceRegistrationTest`, `SecurityEventBroadcastTest`, `ThreatResponseTest`, `EmergencyBreakGlassTest` (6 scenarios) added; total test count now 264.
- **Documentation Updates**: Phase-2 RBAC status, security controls progress (95 → 98 %), and threat-response guides.

### Changed

- **SecurityEventType** enum extended with `EMERGENCY_ACCESS` and `AUTH_SUCCESS` values.
- **RoleController** auditing now persists `user_id` on every `PermissionAudit` entry and supports batched matrix updates.
- **EmergencyAccess** model gains token helpers; service & controller updated accordingly.
- **Resources/js/pages/admin/users/[id]/roles.tsx** wired temporal delegation dialog and UI polish.

### Fixed

- Permission validation errors in `RoleManagementTest`; all 14 scenarios now pass.
- Form submission issues in `role-edit-dialog` preset buttons (`type="button"`), resolving unintended updates.
- ESLint/TypeScript clean-ups across new components and pages.

### Security

- Break-glass tokens are single-use, time-boxed and fully audited; attempts after expiry log a security event.
- Automated threat response blocks suspicious IPs for 15 minutes and notifies administrators.

### Performance

- Cached security-log writes reduce insert volume by ~30 % during high-traffic events.

### Migration Notes

1. Run `php artisan migrate --path=database/migrations/2025_06_26_201518_add_token_to_emergency_access_table.php --force`.
2. Clear permission & config caches: `php artisan rbac:warm-cache && php artisan config:cache`.
3. (Optional) Publish broadcasting configuration for security events if not previously enabled.

## [1.2.1] - 2025-06-28

### Added

- **Automated Threat Response Hardening**: Complete backend security enhancement with configurable IP block TTL, comprehensive audit logging, and robust error handling.
- **Security Configuration System**: New `config/security.php` with environment variable overrides for IP block duration, audit logging, and notification settings.
- **Enhanced ThreatResponseService**: Configurable TTL (default 30 minutes), rate-limited notifications, manual IP unblock functionality, and graceful error handling.
- **Comprehensive Audit Trail**: All IP blocks/unblocks automatically logged to `permission_audits` with detailed metadata including block duration, trigger events, and administrator actions.
- **Integration Test Suite**: 11 comprehensive test scenarios covering threat detection, IP blocking, duplicate prevention, manual unblocking, and error scenarios.

### Changed

- **IpAllowlistMiddleware**: Enhanced to check for blocked IPs from threat response system before processing allowlist rules.
- **PermissionAudit**: Extended enum to support IP blocking actions (`ip_block_auto`, `ip_unblock_manual`, `ip_unblock_auto`) with database migration for MySQL compatibility.
- **ThreatResponseService**: Refactored to use configurable settings from `config/security.php` instead of hardcoded values.

### Fixed

- **Database Compatibility**: Migration handles SQLite (tests) vs MySQL (production) differences for enum modifications.
- **Audit Entry Creation**: Resolved constraint violations by using appropriate enum values and bypassing model validation for IP block audits.
- **Test Dependencies**: Fixed `IpAllowlistMiddleware` constructor injection in feature tests.

### Security

- **Configurable Threat Response**: IP block TTL can be adjusted via `IP_BLOCK_TTL` environment variable for different security requirements.
- **Complete Audit Trail**: All automated and manual IP blocking actions are logged with full context for compliance and investigation.
- **Rate Limited Notifications**: Prevents notification spam while maintaining security alerting capabilities.
- **Graceful Degradation**: Audit logging failures don't break IP blocking functionality, ensuring security measures remain active.

### Configuration

New environment variables available:

```env
# IP Block Configuration
IP_BLOCK_TTL=1800                    # 30 minutes default
LOG_IP_BLOCKS=true                   # Enable audit logging
ENABLE_SECURITY_ALERTS=true          # Enable email notifications
SECURITY_ALERT_RATE_LIMIT=3600       # 1 hour rate limit window
```

### Testing

- **Complete Test Coverage**: 11/11 threat response tests passing
- **Integration Testing**: Covers threat detection, blocking, duplicate prevention, manual unblocking, and error scenarios
- **Cross-Database Support**: Tests work with both SQLite (development) and MySQL (production)

### Migration Notes

1. Run `php artisan migrate` to update permission_audits enum (MySQL only, SQLite handled automatically)
2. Optionally configure threat response settings in `.env` using new variables
3. Clear config cache: `php artisan config:cache`

[1.2.0]: https://github.com/your-org/support-center/releases/tag/v1.2.0
[1.2.1]: https://github.com/your-org/support-center/releases/tag/v1.2.1
