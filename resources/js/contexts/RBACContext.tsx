import { Department, Role, User } from '@/types/rbac';
import { createContext, ReactNode, useContext, useEffect, useState } from 'react';

export interface RBACContextType {
    user: User | null;
    userRoles: Role[];
    userPermissions: string[];
    departments: Department[];
    loading: boolean;
    hasPermission: (permission: string) => boolean;
    hasRole: (role: string) => boolean;
    hasAnyRole: (roles: string[]) => boolean;
    hasAnyPermission: (permissions: string[]) => boolean;
    canAccess: (resource: string, action: string) => boolean;
    canManageUser: (targetUser: User) => boolean;
    canAccessDepartment: (departmentId: number) => boolean;
    refreshPermissions: () => Promise<void>;
}

const RBACContext = createContext<RBACContextType | undefined>(undefined);

export interface RBACProviderProps {
    children: ReactNode;
    initialUser: User | null;
}

export function RBACProvider({ children, initialUser }: RBACProviderProps) {
    const [user, setUser] = useState<User | null>(initialUser);
    const [userRoles, setUserRoles] = useState<Role[]>([]);
    const [userPermissions, setUserPermissions] = useState<string[]>([]);
    const [departments, setDepartments] = useState<Department[]>([]);
    const [loading, setLoading] = useState(false);

    /**
     * Check if user has a specific permission
     */
    const hasPermission = (permission: string): boolean => {
        if (!user) return false;
        return userPermissions.includes(permission) || userPermissions.includes('*');
    };

    /**
     * Check if user has a specific role
     */
    const hasRole = (role: string): boolean => {
        if (!user) return false;
        return userRoles.some((r) => r.name === role);
    };

    /**
     * Check if user has any of the specified roles
     */
    const hasAnyRole = (roles: string[]): boolean => {
        if (!user) return false;
        return roles.some((role) => hasRole(role));
    };

    /**
     * Check if user has any of the specified permissions
     */
    const hasAnyPermission = (permissions: string[]): boolean => {
        if (!user) return false;
        return permissions.some((permission) => hasPermission(permission));
    };

    /**
     * Check if user can access a resource with a specific action
     */
    const canAccess = (resource: string, action: string): boolean => {
        if (!user) return false;
        const permission = `${resource}.${action}`;
        return hasPermission(permission) || hasPermission(`${resource}.*`);
    };

    /**
     * Check if user can manage another user (based on hierarchy and department)
     */
    const canManageUser = (targetUser: User): boolean => {
        if (!user) return false;

        // Super admin can manage everyone
        if (hasPermission('users.manage_all')) return true;

        // Department managers can manage users in their department
        if (hasPermission('users.manage_department') && user.department_id === targetUser.department_id) {
            return true;
        }

        // Users can manage themselves
        return user.id === targetUser.id;
    };

    /**
     * Check if user can access a specific department
     */
    const canAccessDepartment = (departmentId: number): boolean => {
        if (!user) return false;

        // Global access
        if (hasPermission('departments.view_all')) return true;

        // Department-specific access
        if (hasPermission('departments.view_department')) {
            return user.department_id === departmentId;
        }

        return false;
    };

    /**
     * Refresh user permissions from the server
     */
    const refreshPermissions = async (): Promise<void> => {
        if (!user) return;

        setLoading(true);
        try {
            const response = await fetch('/api/user/permissions', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            setUserRoles(data.roles || []);
            setUserPermissions(data.permissions || []);
            setDepartments(data.departments || []);
        } catch (error) {
            console.error('Failed to refresh permissions:', error);
            // In case of error, clear sensitive data
            setUserRoles([]);
            setUserPermissions([]);
        } finally {
            setLoading(false);
        }
    };

    /**
     * Initialize permissions when user changes
     */
    useEffect(() => {
        if (user) {
            refreshPermissions();
        } else {
            // Clear data when user logs out
            setUserRoles([]);
            setUserPermissions([]);
            setDepartments([]);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [user]);

    /**
     * Handle user updates from external sources
     */
    useEffect(() => {
        setUser(initialUser);
    }, [initialUser]);

    const value: RBACContextType = {
        user,
        userRoles,
        userPermissions,
        departments,
        loading,
        hasPermission,
        hasRole,
        hasAnyRole,
        hasAnyPermission,
        canAccess,
        canManageUser,
        canAccessDepartment,
        refreshPermissions,
    };

    return <RBACContext.Provider value={value}>{children}</RBACContext.Provider>;
}

/**
 * Hook to use RBAC context
 */
export const useRBAC = (): RBACContextType => {
    const context = useContext(RBACContext);
    if (context === undefined) {
        throw new Error('useRBAC must be used within a RBACProvider');
    }
    return context;
};

/**
 * Hook for permission checking with loading state
 */
export const usePermission = (permission: string) => {
    const { hasPermission, loading } = useRBAC();
    return {
        hasPermission: hasPermission(permission),
        loading,
    };
};

/**
 * Hook for role checking with loading state
 */
export const useRole = (role: string) => {
    const { hasRole, loading } = useRBAC();
    return {
        hasRole: hasRole(role),
        loading,
    };
};
