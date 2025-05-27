export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    created_at: string;
    updated_at: string;
    two_factor_enabled: boolean;
    webauthn_enabled?: boolean;
    preferred_mfa_method?: string;
}

export interface LoginCredentials {
    email: string;
    password: string;
    remember?: boolean;
}

export interface AuthState {
    user: User | null;
    loading: boolean;
    error: string | null;
    twoFactorRequired: boolean;
}
