import api from '@/lib/axios';
import { AuthState, LoginCredentials, User } from '@/types/auth';
import { router } from '@inertiajs/react';
import { AxiosError } from 'axios';
import { useCallback, useState } from 'react';

interface UseAuthReturn extends AuthState {
    login: (credentials: LoginCredentials) => Promise<boolean>;
    logout: () => Promise<void>;
    getUser: () => Promise<User | null>;
}

export function useAuth(): UseAuthReturn {
    const [user, setUser] = useState<User | null>(null);
    const [loading, setLoading] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);

    const getUser = useCallback(async (): Promise<User | null> => {
        try {
            const response = await api.get('/api/user');
            const userData = response.data as User;
            setUser(userData);
            return userData;
        } catch (err) {
            console.error('Failed to fetch user:', err);
            setUser(null);
            return null;
        }
    }, []);

    const login = useCallback(
        async (credentials: LoginCredentials): Promise<boolean> => {
            setLoading(true);
            setError(null);
            try {
                await api.get('/sanctum/csrf-cookie');
                const loginResponse = await api.post('/login', credentials);

                // Check if we got a redirect URL in the response
                const redirectUrl = loginResponse.data?.redirectTo || '/dashboard';

                // Get the user data
                const userData = await getUser();

                if (userData) {
                    router.visit(redirectUrl);
                    return true;
                }

                return !!userData;
            } catch (err) {
                console.error('Login failed:', err);
                let message = 'An unexpected error occurred. Please try again.';
                if (err instanceof AxiosError && err.response) {
                    if (err.response.data && typeof err.response.data.message === 'string') {
                        message = err.response.data.message;
                    } else if (err.response.status === 422) {
                        message = 'Invalid credentials. Please check your email and password.';
                    }
                }
                setError(message);
                return false;
            } finally {
                setLoading(false);
            }
        },
        [getUser],
    );

    const logout = useCallback(async (): Promise<void> => {
        setLoading(true);
        setError(null);
        try {
            await api.post('/logout');
            setUser(null);
            router.visit('/');
        } catch (err) {
            console.error('Logout failed:', err);
            setError('Logout failed. Please try again.');
        } finally {
            setLoading(false);
        }
    }, []);

    return {
        user,
        loading,
        error,
        login,
        logout,
        getUser,
    };
}
