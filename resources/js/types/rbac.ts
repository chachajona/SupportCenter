import { User as AuthUser } from '@/types/auth';

export interface Role {
    id: number;
    name: string;
    display_name: string;
    description: string;
    hierarchy_level: number;
    is_active: boolean;
    permissions: Permission[];
    users_count?: number;
    created_at: string;
    updated_at: string;
}

export interface Permission {
    id: number;
    name: string;
    display_name: string;
    description: string;
    resource: string;
    action: string;
    is_active: boolean;
    roles?: Role[];
    created_at: string;
    updated_at: string;
}

export interface Department {
    id: number;
    name: string;
    parent_id?: number;
    path: string;
    manager_id?: number;
    is_active: boolean;
    manager?: User;
    parent?: Department;
    children?: Department[];
    users_count?: number;
    hierarchy_path?: string;
}

export interface UserRole {
    id: number;
    user_id: number;
    role_id: number;
    role: Role;
    granted_by?: number;
    granted_by_user?: User;
    granted_at: string;
    expires_at?: string;
    delegation_reason?: string;
    is_active: boolean;
}

export interface EmergencyAccess {
    id: number;
    user_id: number;
    user?: User;
    permissions: string[];
    reason: string;
    granted_by: number;
    granted_by_user?: User;
    granted_at: string;
    expires_at: string;
    used_at?: string;
    is_active: boolean;
}

export interface PermissionAudit {
    id: number;
    user_id: number;
    user?: User;
    permission_id?: number;
    permission?: Permission;
    role_id?: number;
    role?: Role;
    action: 'granted' | 'revoked' | 'modified';
    old_values?: Record<string, unknown>;
    new_values?: Record<string, unknown>;
    ip_address?: string;
    user_agent?: string;
    performed_by: number;
    performed_by_user?: User;
    reason?: string;
    created_at: string;
}

export interface RBACStats {
    total_users: number;
    total_roles: number;
    total_permissions: number;
    active_emergency_access: number;
    recent_audits: number;
}

export interface AuditStats {
    total_events: number;
    recent_events: number;
    high_risk_events: number;
    active_users: number;
}

// Enhanced User interface with RBAC fields - extends auth User
export interface User extends AuthUser {
    department_id?: number;
    department?: Department;
    is_active: boolean;
    last_login_at?: string;
    roles?: Role[];
    permissions?: string[];
}

// Permission checking utilities
export interface PermissionCheckOptions {
    resource?: string;
    action?: string;
    permission?: string;
    role?: string;
    anyOf?: string[];
    allOf?: string[];
}

// Role assignment interfaces
export interface RoleAssignmentRequest {
    user_id: number;
    role_ids: number[];
    reason?: string;
    expires_at?: string;
}

export interface TemporalAccessRequest {
    user_id: number;
    role_id: number;
    duration_minutes: number;
    reason: string;
}

// Audit filtering
export interface AuditFilters {
    user?: string;
    action?: 'granted' | 'revoked' | 'modified';
    date_from?: string;
    date_to?: string;
    role_id?: number;
    permission_id?: number;
}
