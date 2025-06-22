import { useRBAC } from '@/contexts/RBACContext';
import React, { ReactNode } from 'react';

export interface PermissionGateProps {
    /**
     * Single permission to check
     */
    permission?: string;

    /**
     * Single role to check
     */
    role?: string;

    /**
     * Resource and action to check (alternative to permission)
     */
    resource?: string;
    action?: string;

    /**
     * Array of permissions - user needs ANY of these
     */
    anyOf?: string[];

    /**
     * Array of permissions - user needs ALL of these
     */
    allOf?: string[];

    /**
     * Array of roles - user needs ANY of these
     */
    anyRole?: string[];

    /**
     * Array of roles - user needs ALL of these
     */
    allRoles?: string[];

    /**
     * Content to show when access is denied
     */
    fallback?: ReactNode;

    /**
     * Content to show while loading permissions
     */
    loadingFallback?: ReactNode;

    /**
     * Whether to show loading state
     */
    showLoading?: boolean;

    /**
     * Children to render when access is granted
     */
    children: ReactNode;

    /**
     * Custom function for complex permission logic
     */
    condition?: () => boolean;
}

/**
 * PermissionGate component that conditionally renders content based on user permissions
 *
 * Features:
 * - Multiple permission checking modes (single, any, all)
 * - Role-based access control
 * - Resource-action based permissions
 * - Loading states
 * - Accessible fallback content
 * - Custom condition support
 */
export function PermissionGate({
    permission,
    role,
    resource,
    action,
    anyOf,
    allOf,
    anyRole,
    allRoles,
    fallback = null,
    loadingFallback = null,
    showLoading = true,
    children,
    condition,
}: PermissionGateProps) {
    const { hasPermission, hasRole, canAccess, hasAnyPermission, hasAnyRole, loading } = useRBAC();

    // Show loading state if permissions are still being fetched
    if (loading && showLoading) {
        return loadingFallback ? <>{loadingFallback}</> : null;
    }

    /**
     * Determine if user has access based on provided props
     */
    const hasAccess = (): boolean => {
        // Custom condition takes precedence
        if (condition) {
            return condition();
        }

        // Single permission check
        if (permission) {
            return hasPermission(permission);
        }

        // Single role check
        if (role) {
            return hasRole(role);
        }

        // Resource-action based permission
        if (resource && action) {
            return canAccess(resource, action);
        }

        // Check any of the provided permissions
        if (anyOf && anyOf.length > 0) {
            return hasAnyPermission(anyOf);
        }

        // Check all of the provided permissions
        if (allOf && allOf.length > 0) {
            return allOf.every((p) => hasPermission(p));
        }

        // Check any of the provided roles
        if (anyRole && anyRole.length > 0) {
            return hasAnyRole(anyRole);
        }

        // Check all of the provided roles
        if (allRoles && allRoles.length > 0) {
            return allRoles.every((r) => hasRole(r));
        }

        // Default to denied if no conditions specified
        return false;
    };

    const hasUserAccess = hasAccess();

    // Add ARIA attributes for accessibility
    const wrapperProps = {
        'aria-hidden': !hasUserAccess || undefined,
        role: 'region' as const,
        'aria-label': !hasUserAccess && fallback ? 'Access restricted content' : undefined,
    };

    return <div {...wrapperProps}>{hasUserAccess ? children : fallback}</div>;
}

/**
 * Higher-order component for permission-based conditional rendering
 */
export function withPermissionGate<P extends object>(Component: React.ComponentType<P>, gateProps: Omit<PermissionGateProps, 'children'>) {
    return function PermissionWrappedComponent(props: P) {
        return (
            <PermissionGate {...gateProps}>
                <Component {...props} />
            </PermissionGate>
        );
    };
}

/**
 * Hook for imperative permission checking
 */
export function usePermissionGate(gateProps: Omit<PermissionGateProps, 'children' | 'fallback'>) {
    const { hasPermission, hasRole, canAccess, hasAnyPermission, hasAnyRole, loading } = useRBAC();

    const checkAccess = (): boolean => {
        const { permission, role, resource, action, anyOf, allOf, anyRole, allRoles, condition } = gateProps;

        if (condition) return condition();
        if (permission) return hasPermission(permission);
        if (role) return hasRole(role);
        if (resource && action) return canAccess(resource, action);
        if (anyOf?.length) return hasAnyPermission(anyOf);
        if (allOf?.length) return allOf.every((p) => hasPermission(p));
        if (anyRole?.length) return hasAnyRole(anyRole);
        if (allRoles?.length) return allRoles.every((r) => hasRole(r));

        return false;
    };

    return {
        hasAccess: checkAccess(),
        loading,
    };
}
