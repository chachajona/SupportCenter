export interface Role {
    id: number;
    name: string;
    display_name: string;
    hierarchy_level: number;
    description?: string;
    permissions?: {
        id: number;
        name: string;
        resource: string;
    }[];
}

export interface Department {
    id: number;
    name: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    roles?: Role[];
    department?: Department;
    two_factor_enabled: boolean;
    webauthn_enabled?: boolean;
    last_login_at?: string;
    permissions?: string[];
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
