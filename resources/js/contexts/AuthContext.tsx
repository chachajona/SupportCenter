import { useAuth } from '@/hooks/use-auth';
import { LoginCredentials, User } from '@/types/auth';
import { createContext, ReactNode, useContext, useEffect, useState } from 'react';

interface AuthContextType {
    user: User | null;
    loading: boolean;
    error: string | null;
    twoFactorRequired: boolean;
    login: (credentials: LoginCredentials) => Promise<boolean>;
    logout: () => Promise<void>;
    getUser: () => Promise<User | null>;
    confirmTwoFactor: (code: string) => Promise<boolean>;
    setTwoFactorRequired: (isRequired: boolean) => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

interface AuthProviderProps {
    children: ReactNode;
}

export function AuthProvider({ children }: AuthProviderProps) {
    const auth = useAuth();
    const { getUser } = auth;
    const [initialized, setInitialized] = useState(false);

    useEffect(() => {
        const isLoginPage = window.location.pathname === '/login';

        if (!initialized) {
            if (!isLoginPage) {
                getUser().finally(() => {
                    setInitialized(true);
                });
            } else {
                setInitialized(true);
            }
        }
    }, [initialized, getUser]);

    if (!initialized) {
        return null;
    }

    return <AuthContext.Provider value={auth}>{children}</AuthContext.Provider>;
}

export function useAuthContext(): AuthContextType {
    const context = useContext(AuthContext);

    if (context === undefined) {
        throw new Error('useAuthContext must be used within an AuthProvider');
    }

    return context;
}
