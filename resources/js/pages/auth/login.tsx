import { Head } from '@inertiajs/react';
import { toast } from 'sonner';

import { LoginForm } from '@/components/auth/login-form';
import { useAuthContext } from '@/contexts/AuthContext';
import { TwoFactorChallenge } from '@/pages/auth/two-factor-challenge';
import AuthLayout from '@/layouts/auth-layout';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { twoFactorRequired } = useAuthContext();

    const handleLoginSuccess = () => {
        toast.success('Welcome back!');
    };

    return (
        <AuthLayout
            title={twoFactorRequired ? 'Two-Factor Challenge' : 'Log in to your account'}
            description={twoFactorRequired ? 'Enter the code from your authenticator app.' : 'Enter your email and password below to log in'}
        >
            <Head title={twoFactorRequired ? '2FA Challenge' : 'Log in'} />

            {twoFactorRequired ? <TwoFactorChallenge /> : <LoginForm onLoginSuccess={handleLoginSuccess} canResetPassword={canResetPassword} />}

            {status && !twoFactorRequired && <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>}
        </AuthLayout>
    );
}
