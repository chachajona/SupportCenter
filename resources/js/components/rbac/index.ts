// Core RBAC Components
export {
    AuditLogSkeleton,
    LoadingSkeleton,
    PermissionTableSkeleton,
    RBACDashboardSkeleton,
    RoleCardsSkeleton,
    UserListSkeleton,
} from './loading-skeleton';
export { PermissionGate } from './permission-gate';
export { PermissionStatus } from './permission-status';

// Role Management Components
export { PermissionMatrix } from './permission-matrix';
export { RoleEditDialog } from './role-edit-dialog';
export { RoleHierarchyView } from './role-hierarchy-view';

// Permission Management Components
export { PermissionEditDialog } from './permission-edit-dialog';

// Re-export types for convenience
export type { Department, Permission, Role, UserRole } from '@/types/rbac';

export { AuditDetailDialog } from './audit-detail-dialog';
export { EmergencyAccessDetailsDialog } from './emergency-access-detail-dialog';
export { EmergencyAccessGrantDialog } from './emergency-access-grant-dialog';
export { default as PermissionPresets } from './permission-presets';
export { RoleAssignmentDialog } from './role-assignment-dialog';
export { RouteGuard } from './route-guard';
export { TemporalAccessForm } from './temporal-access-form';
export { TemporalAccessRequests } from './temporal-access-requests';
