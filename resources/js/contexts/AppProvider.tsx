import { User as RBACUser } from '@/types/rbac';
import { ReactNode } from 'react';
import { AuthProvider, useAuthContext } from './AuthContext';
import { RBACProvider } from './RBACContext';

interface AppProviderProps {
    children: ReactNode;
}

/**
 * Internal component that connects Auth and RBAC contexts
 * This needs to be inside AuthProvider to access the auth context
 */
function RBACWrapper({ children }: { children: ReactNode }) {
    const { user } = useAuthContext();

    // Convert auth User to RBAC User (they're compatible due to interface extension)
    const rbacUser = user
        ? {
              ...user,
              department_id: user.department?.id,
              department: user.department
                  ? {
                        ...user.department,
                        path: '',
                        is_active: true,
                    }
                  : undefined,
              is_active: user.is_active ?? true,
              last_login_at: user.last_login_at,
              roles: user.roles || [],
              permissions: user.permissions || [],
          }
        : null;

    return <RBACProvider initialUser={rbacUser as RBACUser}>{children}</RBACProvider>;
}

/**
 * Main app provider that combines Authentication and RBAC
 * This should wrap your entire application
 */
export function AppProvider({ children }: AppProviderProps) {
    return (
        <AuthProvider>
            <RBACWrapper>{children}</RBACWrapper>
        </AuthProvider>
    );
}

/**
 * Hook to access both auth and RBAC contexts
 */
export function useApp() {
    const auth = useAuthContext();

    return {
        ...auth,
        // You can add any app-level methods here
    };
}
