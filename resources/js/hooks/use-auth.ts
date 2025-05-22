import api from '@/lib/axios';
import { AuthState, LoginCredentials, User } from '@/types/auth';
import { router } from '@inertiajs/react';
import { AxiosError } from 'axios';
import { useCallback, useState } from 'react';

interface UseAuthReturn extends AuthState {
    login: (credentials: LoginCredentials) => Promise<boolean>;
    logout: () => Promise<void>;
    getUser: () => Promise<User | null>;
    confirmTwoFactor: (token: string) => Promise<boolean>;
    setTwoFactorRequired: (isRequired: boolean) => void;
}

export function useAuth(): UseAuthReturn {
    const [user, setUser] = useState<User | null>(null);
    const [loading, setLoading] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);
    const [twoFactorRequired, setTwoFactorRequired] = useState<boolean>(false);

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
            setTwoFactorRequired(false);
            try {
                await api.get('/sanctum/csrf-cookie');
                const loginResponse = await api.post('/login', credentials);

                if (loginResponse.data?.two_factor === true) {
                    setTwoFactorRequired(true);
                    return false;
                } else {
                    const userData = await getUser();
                    if (userData) {
                        let redirectUrl = '/dashboard';
                        const potentialRedirect = loginResponse.data?.redirectTo;

                        if (
                            typeof potentialRedirect === 'string' &&
                            potentialRedirect.startsWith('/') &&
                            !potentialRedirect.startsWith('//') &&
                            !/[\n\r]/.test(potentialRedirect)
                        ) {
                            redirectUrl = potentialRedirect;
                        } else if (potentialRedirect) {
                            // Only warn if a redirect was provided but was invalid
                            console.warn('Backend provided an invalid or unsafe redirect URL. Defaulting to /dashboard.', potentialRedirect);
                        }

                        router.visit(redirectUrl);
                        return true;
                    }
                    setError('Failed to retrieve user data after login.');
                    return false;
                }
            } catch (err) {
                console.error('Login failed:', err);
                let message = 'An unexpected error occurred. Please try again.';
                if (err instanceof AxiosError && err.response) {
                    if (err.response.data && typeof err.response.data.message === 'string') {
                        message = err.response.data.message;
                    } else if (err.response.status === 422) {
                        const errors = err.response.data.errors;
                        if (errors && typeof errors === 'object') {
                            message = Object.values(errors).flat().join(' ');
                        } else {
                            message = 'Invalid credentials or input.';
                        }
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

    const confirmTwoFactor = useCallback(
        async (token: string): Promise<boolean> => {
            setLoading(true);
            setError(null);
            try {
                const payload = /^\d{6}$/.test(token)
                    ? { code: token } // numeric TOTP
                    : { recovery_code: token }; // fallback
                const response = await api.post('/two-factor-challenge', payload);

                setTwoFactorRequired(false);
                const userData = await getUser();

                if (userData) {
                    let redirectUrl = '/dashboard';
                    const potentialRedirect = response.data?.redirectTo;
                    if (
                        typeof potentialRedirect === 'string' &&
                        potentialRedirect.startsWith('/') &&
                        !potentialRedirect.startsWith('//') &&
                        !/[\n\r]/.test(potentialRedirect)
                    ) {
                        redirectUrl = potentialRedirect;
                    } else if (potentialRedirect) {
                        console.warn('Backend provided an invalid or unsafe redirect URL. Defaulting to /dashboard.', potentialRedirect);
                    }
                    router.visit(redirectUrl);
                    return true;
                }
                setError('Failed to retrieve user data after 2FA confirmation.');
                return false;
            } catch (err) {
                console.error('2FA confirmation failed:', err);
                let message = 'Invalid 2FA code or an error occurred.';
                if (err instanceof AxiosError && err.response) {
                    if (err.response.data && typeof err.response.data.message === 'string') {
                        message = err.response.data.message;
                    } else if (err.response.status === 422) {
                        const errors = err.response.data.errors;
                        if (errors && errors.code && Array.isArray(errors.code)) {
                            message = errors.code.join(' ');
                        } else if (errors && typeof errors === 'object') {
                            message = Object.values(errors).flat().join(' ');
                        } else {
                            message = 'The provided 2FA code was invalid.';
                        }
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
            setTwoFactorRequired(false);
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
        twoFactorRequired,
        login,
        logout,
        getUser,
        confirmTwoFactor,
        setTwoFactorRequired,
    };
}
