import { Alert, AlertDescription } from '@/components/ui/alert';
import { useRBAC } from '@/contexts/RBACContext';
import { router } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import React from 'react';

interface RouteGuardProps {
    permissions?: string[];
    roles?: string[];
    requireAll?: boolean; // If true, user must have ALL specified permissions/roles
    fallback?: React.ReactNode;
    redirectTo?: string;
    children: React.ReactNode;
}

export function RouteGuard({ permissions = [], roles = [], requireAll = false, fallback, redirectTo = '/unauthorized', children }: RouteGuardProps) {
    const { hasPermission, hasRole, hasAnyPermission, hasAnyRole } = useRBAC();

    // Check permissions
    const hasRequiredPermissions = () => {
        if (permissions.length === 0) return true;

        if (requireAll) {
            return permissions.every((permission) => hasPermission(permission));
        } else {
            return hasAnyPermission(permissions);
        }
    };

    // Check roles
    const hasRequiredRoles = () => {
        if (roles.length === 0) return true;

        if (requireAll) {
            return roles.every((role) => hasRole(role));
        } else {
            return hasAnyRole(roles);
        }
    };

    const hasAccess = hasRequiredPermissions() && hasRequiredRoles();

    if (!hasAccess) {
        if (redirectTo && redirectTo !== window.location.pathname) {
            router.visit(redirectTo);
            return null;
        }

        if (fallback) {
            return <>{fallback}</>;
        }

        return (
            <div className="flex min-h-[400px] items-center justify-center">
                <div className="w-full max-w-md">
                    <Alert variant="destructive">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertDescription>
                            You don't have permission to access this page. Contact your administrator if you believe this is an error.
                        </AlertDescription>
                    </Alert>
                </div>
            </div>
        );
    }

    return <>{children}</>;
}

// Higher-order component for route protection
export function withRouteGuard<P extends object>(WrappedComponent: React.ComponentType<P>, guardProps: Omit<RouteGuardProps, 'children'>) {
    return function GuardedComponent(props: P) {
        return (
            <RouteGuard {...guardProps}>
                <WrappedComponent {...props} />
            </RouteGuard>
        );
    };
}

// Hook for programmatic access checking
export function useRouteAccess(permissions: string[] = [], roles: string[] = [], requireAll = false) {
    const { hasPermission, hasRole, hasAnyPermission, hasAnyRole } = useRBAC();

    const hasRequiredPermissions = () => {
        if (permissions.length === 0) return true;

        if (requireAll) {
            return permissions.every((permission) => hasPermission(permission));
        } else {
            return hasAnyPermission(permissions);
        }
    };

    const hasRequiredRoles = () => {
        if (roles.length === 0) return true;

        if (requireAll) {
            return roles.every((role) => hasRole(role));
        } else {
            return hasAnyRole(roles);
        }
    };

    return {
        hasAccess: hasRequiredPermissions() && hasRequiredRoles(),
        hasRequiredPermissions: hasRequiredPermissions(),
        hasRequiredRoles: hasRequiredRoles(),
    };
}
