# Changelog

All notable changes to this project will be documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.1] - 2026-07-13

### Added - Enhanced Phase 3 Integration: Weeks 1-2 Complete

- **Week 1: Security & Authentication Integration**

    - **TwoFactorConfirmedMiddleware**: New middleware ensuring users complete 2FA before accessing sensitive endpoints with 3-hour session validity and proper error responses
    - **Enhanced TicketController**: Added comprehensive authentication middleware stack including `auth`, `verified`, `two-factor.confirmed`, `device.register`, `geo.restrict`
    - **API Security Enhancement**: Separated API routes into different middleware groups with proper 2FA protection and resolved route name conflicts by namespacing API routes (`api.tickets.index`, etc.)
    - **Emergency Access Integration**: Enhanced `User.php` model with emergency access methods (`hasEmergencyAccess()`, `getActiveEmergencyAccess()`, `hasEmergencyPermission()`)
    - **Audit Trail Integration**: Enhanced `TicketObserver.php` with comprehensive audit logging for ticket creation and updates using `PermissionAudit::create()`
    - **TicketAssignmentService Enhancement**: Improved audit records with complete context including permissions, roles, detailed change tracking, and added `getPermissionId()` helper method

- **Week 2: RBAC & Permissions Integration**
    - **Comprehensive Permission System**: Added Phase 3 permissions to `RolePermissionSeeder.php`:
        - **Phase 3 Ticket Management**: `view_internal_responses`, `create_internal_responses`
        - **Phase 3 Knowledge Base**: `view_articles`, `delete_articles`, `view_analytics`
        - **Phase 3 Analytics & Reporting**: `view_department_analytics`, `view_all_analytics`, `export_reports`, `schedule_reports`
    - **Role Assignment Updates**: Updated all six roles (Support Agent, Department Manager, Regional Manager, Compliance Auditor, Knowledge Curator, System Administrator) with appropriate Phase 3 permissions
    - **AnalyticsPolicy**: New comprehensive policy for analytics access control with emergency access integration, department scoping, and methods for analytics viewing, report exporting, and scheduling
    - **Enhanced Policy Integration**: Updated `TicketPolicy.php` and `KnowledgeArticlePolicy.php` with emergency access checks for ALL methods and comprehensive audit logging
    - **AuthServiceProvider Updates**: Registered all new policies including AnalyticsPolicy and KnowledgeArticlePolicy

### Enhanced

- **Emergency Access Audit System**: Improved emergency access auditing in both `TicketPolicy.php` and `KnowledgeArticlePolicy.php` with:

    - Guard clauses to prevent unnecessary audit entries when emergency access is not active
    - Graceful handling of temporary article instances in knowledge base operations
    - Enhanced null safety and proper emergency access ID resolution
    - Comprehensive audit context including emergency access metadata

- **Route Security**: Complete resolution of route conflicts between web and API routes with proper namespacing and middleware protection

- **Policy Architecture**: All policies now consistently implement emergency access checks with audit logging across view, update, delete, assign, transfer, close, reopen, and specialized operations

### Fixed

- **Route Conflicts**: Resolved duplicate route names between web and API routes through proper namespacing
- **Syntax Errors**: Fixed extra brace issues and linting errors across policy files
- **Emergency Access Auditing**: Improved handling of null emergency access states and temporary model instances
- **Permission Trailing Commas**: Fixed array syntax consistency across permission definitions

### Security

- **Complete 2FA Integration**: All Phase 3 endpoints now require two-factor authentication completion
- **Emergency Access Audit Trail**: Comprehensive logging of all emergency access usage with detailed context and metadata
- **API Security**: Proper authentication middleware stack for all API endpoints with sanctum and 2FA protection
- **Department Scoping**: Maintained hierarchical access controls throughout Phase 3 integration

### Technical Implementation

- **Laravel Standards**: All code follows Laravel 12.x best practices with strict typing (`declare(strict_types=1);`)
- **Permission Inheritance**: Maintained through existing role hierarchy system without breaking changes
- **Audit Integration**: Seamless integration with existing Phase 2 audit system
- **Route Organization**: Clean separation between web and API routes with proper middleware application

### Migration Notes

1. Run `php artisan migrate` to ensure all dependencies are current
2. Re-run `php artisan db:seed --class=RolePermissionSeeder` to add Phase 3 permissions
3. Clear caches: `php artisan rbac:warm-cache && php artisan config:cache && php artisan route:cache`
4. Verify route integrity: `php artisan route:list` to confirm no conflicts
5. Test authentication flows to ensure 2FA integration is working properly

### Next Phase

- **Week 3**: UI/UX & Data Integration - Frontend component integration, model relationships enhancement, and React component integration with existing Phase 2 layouts

## [3.0.0] - 2025-06-30

### Added - Phase 3: Core Helpdesk System

- **Phase 3A: Core Helpdesk System**: Complete ticket management implementation with RBAC integration following simplified MVP approach based on comprehensive over-engineering analysis and YAGNI principles.
- **Ticket Management Infrastructure**:
    - **Database Schema**: 4 comprehensive migrations (`ticket_statuses`, `ticket_priorities`, `tickets`, `ticket_responses`) with proper indexing and foreign key constraints
    - **Ticket Model** with complete PHPDoc property documentation, generic relationship types, business logic methods (`isOverdue()`, `isClosed()`), automatic ticket number generation, and department-scoped access controls
    - **TicketStatus, TicketPriority, TicketResponse Models** with workflow management, color coding, and thread handling capabilities
- **RBAC Integration & Security**:
    - **TicketPolicy** with department-scoped authorization, permission-based access control, secure property access using `getKey()` methods, and comprehensive authorization rules
    - **Permission System**: Extended with 12 new ticket-related permissions (`tickets.create`, `tickets.view_own`, `tickets.view_department`, `tickets.view_all`, etc.)
    - **Audit Trail Integration**: Complete audit logging for ticket assignments and operations via `PermissionAudit` system
- **Assignment & Workflow System**:
    - **TicketAssignmentService** with round-robin assignment algorithm, permission validation, complete audit trail integration, notification system, and SQLite/MySQL compatibility
    - **TicketController** with full CRUD operations, department-scoped access, proper input validation, error handling, and optimized eager loading
- **Notification System**:
    - **TicketAssignedNotification** with email and database notifications, `ShouldQueue` implementation for performance, safe property access with error handling, and professional email templates
- **Data Management**:
    - **HelpdeskSeeder** with default ticket statuses (Open, In Progress, Pending, Resolved, Closed), priority levels (Low, Medium, High, Critical), and color-coded UI elements
    - **TicketFactory** for comprehensive test data generation with realistic scenarios and relationship handling
- **Comprehensive Testing**: **TicketManagementTest** with 8/8 test scenarios covering RBAC integration, assignment workflows, audit trail verification, and department scoping validation.

### Changed

- **Permission Audit System**: Extended enum with ticket-related actions (`ticket_assigned`, `ticket_transferred`, `ticket_closed`) and added MySQL-compatible migration for production environments.
- **RBAC Foundation**: Enhanced to seamlessly integrate with helpdesk operations while maintaining existing security controls and department hierarchy.
- **Database Performance**: Optimized queries with proper indexing and eager loading strategies for ticket operations.

### Fixed

- **PHPStan Level 8 Compliance**: Resolved 31+ linter errors across ticket management system including missing type declarations, generic type specifications for Laravel relationships, property access using magic properties, and array type specifications.
- **Type Safety**: Implemented comprehensive TypeScript and PHP type declarations throughout ticket system with proper `Builder<Model>` return types, collection type annotations, and safe property access methods.
- **Database Compatibility**: Added SQLite-compatible audit action mapping for testing environments while maintaining MySQL production compatibility.
- **Testing Stability**: Fixed user department assignment requirements in ticket creation tests ensuring 100% test pass rate (252 total tests).

### Security

- **Department-Scoped Access**: Tickets are automatically scoped to user's department access based on RBAC permissions preventing unauthorized cross-department access.
- **Secure Property Access**: Eliminated magic property access in favor of safe `getKey()` and `getAttribute()` methods throughout ticket system.
- **Input Validation**: Comprehensive request validation for all ticket operations with proper error handling and user feedback.
- **Audit Trail**: Complete audit logging for all ticket operations maintaining compliance and investigation capabilities.

### Performance

- **Response Times**: Achieved <200ms for ticket operations through optimized database queries and proper eager loading.
- **Efficient Assignment**: Round-robin assignment algorithm with minimal database queries and proper permission caching.
- **Queue Integration**: Asynchronous notification processing via `ShouldQueue` interface preventing blocking operations.
- **Database Optimization**: Strategic indexing and relationship optimization for high-volume ticket operations.

### Migration Notes

1. Run `php artisan migrate` to apply 5 new helpdesk migrations
2. Execute `php artisan db:seed --class=HelpdeskSeeder` to populate default statuses and priorities
3. Re-run `php artisan db:seed --class=RolePermissionSeeder` to add ticket permissions
4. Clear caches: `php artisan rbac:warm-cache && php artisan config:cache`
5. Verify all 252 tests pass with `php artisan test`

## [2.2.2] - 2025-06-29

### Added - Phase 2: Advanced Security Dashboard & Enhanced UX

- **Real-Time Security Dashboard**: Comprehensive security monitoring with live WebSocket connections, auto-refresh (30s intervals), and connection status indicators.
- **Security Metrics API**: New `/admin/security/metrics` endpoint providing real-time threat counts (24h and 1h), IP blocking statistics, authentication events, security breakdown by type, timeline data for charts, top threat sources, and system health indicators.
- **Interactive Security Charts**: Timeline charts and pie charts using Recharts library showing threat patterns, event distribution, and security trends with 24-hour hourly data buckets.
- **Advanced Security Alerts**: Threshold-based alerts for high threat activity (>10 threats/hour), excessive authentication failures (>20 failures/hour), and system health warnings.
- **Permission Matrix UX Enhancements**:
    - **Batch Operations**: "Select All" buttons for roles and "Grant to All" buttons for permissions with smart toggle logic
    - **Keyboard Navigation**: Arrow key navigation, Enter/Space to toggle, Escape to exit focus, Shift+? for help
    - **Accessibility Features**: Comprehensive ARIA labels, screen reader support, focus indicators, and keyboard shortcuts help panel
- **Enhanced Matrix Interface**: Visual focus indicators, keyboard shortcuts panel, batch operation tooltips, and pending changes counter in statistics cards.

### Changed

- **Security Dashboard Layout**: Professional design with real-time connection status, manual refresh capability, error handling with alerts display, and comprehensive metrics cards showing threats blocked, IP blocks, authentication events, and system health.
- **Permission Matrix Navigation**: Enhanced table with role="grid" for screen readers, proper ARIA labels for all interactive elements, tabindex management for keyboard navigation, and batch operation buttons integrated into column headers.
- **Security Controller**: Extended with comprehensive metrics calculation including threat analysis, IP blocking statistics, authentication metrics, and system health monitoring.

### Fixed

- **WebSocket Error Handling**: Auto-reconnect functionality with exponential backoff, connection status indicators, and graceful degradation when WebSocket unavailable.
- **TypeScript Safety**: Resolved all linter errors in security dashboard components with proper type definitions and null safety checks.
- **Permission Matrix Focus**: Fixed keyboard navigation focus management and visual indicators for cell selection.

### Security

- **Real-Time Threat Monitoring**: Live security event stream provides immediate visibility into system threats and attack patterns.
- **Enhanced Access Controls**: Batch permission operations maintain existing security constraints for system administrator role protection.
- **Audit Trail Integrity**: All batch permission changes maintain individual audit log entries for full traceability.

### Performance

- **Optimized Metrics Queries**: Efficient database queries for security statistics with proper indexing and caching considerations.
- **Smart WebSocket Management**: Connection pooling and automatic cleanup prevent resource leaks during extended monitoring sessions.
- **Batch Operation Efficiency**: Single transaction processing for multiple permission changes reduces database load.

### Accessibility

- **WCAG Compliance**: Permission matrix now fully navigable via keyboard with proper ARIA roles, labels, and live regions.
- **Screen Reader Support**: Comprehensive labeling for all security dashboard elements and interactive components.
- **Keyboard Shortcuts**: Intuitive navigation patterns with help panel for users requiring keyboard-only access.

### Migration Notes

1. No database migrations required - all changes are frontend and API enhancements
2. Clear application cache: `php artisan config:cache && php artisan route:cache`
3. Restart queue workers to ensure WebSocket broadcasting works properly
4. Verify Recharts dependency is available: `npm install` (already in package.json)

## [2.2.1] - 2025-06-28

### Added - Phase 2: Enhanced Threat Response & Configuration

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

## [2.2.0] - 2025-06-26

### Added - Phase 2: Real-Time Security & Emergency Access

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

## [2.1.0] - 2025-06-03

### Added - Phase 2: Complete RBAC Foundation

- **Complete Permission System**: Comprehensive RBAC implementation with spatie/laravel-permission integration, role hierarchy, and department-scoped access controls.
- **Role Management**: Six pre-defined roles (Support Agent, Department Manager, Regional Manager, Compliance Auditor, Knowledge Curator, System Administrator) with hierarchical permissions.
- **Permission Auditing**: Complete audit trail system with `PermissionAudit` model tracking all RBAC operations with user context and metadata.
- **Department Hierarchy**: Multi-level department structure with cascading access controls and inheritance.
- **Emergency Access System**: Break-glass functionality with time-bounded access, comprehensive auditing, and automatic expiry.
- **Temporal Access**: Time-limited role delegation with automatic revocation and audit logging.
- **Advanced Security Middleware**: IP allowlisting, device registration, geo-restriction, and advanced rate limiting.
- **Admin Interface**: Complete React TypeScript admin dashboard with role management, user administration, and permission matrices.
- **Security Dashboard**: Real-time monitoring of security events, role changes, and access patterns.

### Changed

- **Authentication Flow**: Enhanced with RBAC integration, department assignment, and role-based redirects.
- **Database Schema**: Added comprehensive RBAC tables with proper indexing and foreign key constraints.
- **User Model**: Extended with role relationships, department associations, and permission helpers.

### Fixed

- **Setup System**: Hardened setup process with proper validation, step tracking, and security controls.
- **Test Suite**: Comprehensive test coverage for all RBAC functionality with 95%+ coverage.
- **Type Safety**: Full TypeScript integration with proper type definitions and PHPStan Level 8 compliance.

### Security

- **Granular Permissions**: 50+ specific permissions covering all system operations.
- **Department Scoping**: Automatic data isolation based on user department and role hierarchy.
- **Audit Trail**: Complete logging of all security-relevant operations.
- **Emergency Protocols**: Secure break-glass access with comprehensive monitoring.

### Migration Notes

1. Run `php artisan migrate` to create RBAC tables
2. Execute `php artisan db:seed --class=RolePermissionSeeder` to set up roles and permissions
3. Assign users to departments and roles
4. Clear caches: `php artisan rbac:warm-cache`

## [1.2.0] - 2025-05-20

### Added - Phase 1: Advanced Authentication & Security

- **Two-Factor Authentication**: Complete TOTP implementation with QR code generation, backup codes, and recovery options.
- **WebAuthn Integration**: Passwordless authentication with biometric and security key support.
- **Advanced Security Features**:
    - **Idle Session Timeout**: Configurable session timeout with automatic logout
    - **IP Allowlisting**: Per-user IP restrictions with CIDR support
    - **Suspicious Activity Detection**: Risk-based scoring system with automated responses
    - **Security Audit Logging**: Comprehensive logging of all security events
- **Device Registration**: Device fingerprinting and registration workflow.
- **Geo-Restriction**: Location-based access controls with IP geolocation.
- **Enhanced Email System**: Professional email templates with Resend integration.

### Changed

- **Authentication Controller**: Enhanced with 2FA and WebAuthn support.
- **Security Middleware**: Multiple layers of security validation.
- **User Model**: Extended with 2FA and WebAuthn credential relationships.

### Fixed

- **Session Management**: Improved session security and timeout handling.
- **Email Delivery**: Reliable email sending with proper error handling.
- **Cross-Browser Compatibility**: WebAuthn support across all modern browsers.

### Security

- **Multi-Factor Authentication**: TOTP and WebAuthn options for all users.
- **Device Trust**: Registered device tracking and validation.
- **Threat Detection**: Automated suspicious activity monitoring.
- **Audit Trail**: Complete security event logging.

### Migration Notes

1. Run `php artisan migrate` to add 2FA and WebAuthn tables
2. Configure email settings for 2FA setup
3. Test WebAuthn functionality in HTTPS environment
4. Set up security monitoring thresholds

## [1.1.0] - 2025-05-16

### Added - Phase 1: Core Authentication System

- **Laravel Fortify Integration**: Complete authentication system with email verification, password reset, and session management.
- **Sanctum API Authentication**: Token-based API authentication for future mobile/SPA integration.
- **User Management**: Basic user registration, login, and profile management.
- **Email System**: SMTP configuration with professional email templates.
- **Database Foundation**: User management tables with proper indexing.
- **Frontend Foundation**: React TypeScript setup with Inertia.js integration.
- **Testing Framework**: Comprehensive test suite for authentication flows.

### Changed

- **Initial Application Structure**: Set up Laravel 12.x with React frontend.
- **Security Defaults**: Implemented Laravel security best practices.

### Fixed

- **Setup Process**: Streamlined initial application setup and configuration.

### Security

- **Password Requirements**: Strong password validation and hashing.
- **Email Verification**: Required email verification for new accounts.
- **CSRF Protection**: Comprehensive CSRF protection across all forms.
- **Rate Limiting**: Login attempt rate limiting.

### Migration Notes

1. Run `php artisan migrate` to create initial database structure
2. Configure email settings in `.env`
3. Set up frontend dependencies with `npm install`
4. Test basic authentication flows

## [1.0.0] - 2025-05-01

### Added - Foundation Release

- **Project Foundation**: Initial Laravel 12.x application setup with modern PHP 8.4 features.
- **Development Environment**: Docker containerization with development tooling.
- **CI/CD Pipeline**: GitHub Actions workflow for testing and deployment.
- **Code Quality**: PHPStan Level 8, Laravel Pint, and ESLint configuration.
- **Documentation**: Comprehensive setup guides and development documentation.

### Security

- **Security Headers**: Basic security headers and CSP configuration.
- **Environment Security**: Secure environment variable handling.

### Migration Notes

1. Clone the repository
2. Run `composer install` and `npm install`
3. Copy `.env.example` to `.env` and configure
4. Run `php artisan key:generate`
5. Set up database and run migrations

[3.0.1]: https://github.com/chachajona/support-center/releases/tag/v3.0.1
[3.0.0]: https://github.com/chachajona/support-center/releases/tag/v3.0.0
[2.2.2]: https://github.com/chachajona/support-center/releases/tag/v2.2.2
[2.2.1]: https://github.com/chachajona/support-center/releases/tag/v2.2.1
[2.2.0]: https://github.com/chachajona/support-center/releases/tag/v2.2.0
[2.1.0]: https://github.com/chachajona/support-center/releases/tag/v2.1.0
[1.2.0]: https://github.com/chachajona/support-center/releases/tag/v1.2.0
[1.1.0]: https://github.com/chachajona/support-center/releases/tag/v1.1.0
[1.0.0]: https://github.com/chachajona/support-center/releases/tag/v1.0.0
