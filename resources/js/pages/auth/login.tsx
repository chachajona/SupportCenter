import { Head } from '@inertiajs/react';
import { toast } from 'sonner';

import { LoginForm } from '@/components/auth/login-form';
import { useAuthContext } from '@/contexts/AuthContext';
import AuthLayout from '@/layouts/auth-layout';
import TwoFactorChallenge from '@/pages/auth/two-factor-challenge';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { twoFactorRequired } = useAuthContext();

    const handleLoginSuccess = () => {
        toast.success('Welcome back!');
    };

    if (twoFactorRequired) {
        return <TwoFactorChallenge />;
    }

    return (
        <AuthLayout title="Log in to your account" description="Enter your email and password below to log in">
            <Head title="Log in" />

            <LoginForm onLoginSuccess={handleLoginSuccess} canResetPassword={canResetPassword} />

            {status && <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>}
        </AuthLayout>
    );
}
