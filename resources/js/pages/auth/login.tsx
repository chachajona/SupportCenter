import { Head } from '@inertiajs/react';
import { toast } from 'sonner';

import { LoginForm } from '@/components/auth/login-form';
import AuthLayout from '@/layouts/auth-layout';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const handleLoginSuccess = () => {
        toast.success('Welcome back!');
    };

    return (
        <AuthLayout title="Log in to your account" description="Enter your email and password below to log in">
            <Head title="Log in" />

            <LoginForm onLoginSuccess={handleLoginSuccess} canResetPassword={canResetPassword} />

            {status && <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>}
        </AuthLayout>
    );
}
