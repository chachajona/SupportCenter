import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AuthLayout from '@/layouts/auth-layout';
import { Head, router } from '@inertiajs/react';
import { Fingerprint, Key, Shield } from 'lucide-react';
import { useState } from 'react';

interface Props {
    user: {
        name: string;
        email: string;
    };
    availableMethods: string[];
    canUseRecoveryCode: boolean;
}

export default function TwoFactorChoice({ user, availableMethods, canUseRecoveryCode }: Props) {
    const [isLoading, setIsLoading] = useState(false);
    const [selectedMethod, setSelectedMethod] = useState<string | null>(null);

    const handleMethodSelect = (method: string) => {
        setIsLoading(true);
        setSelectedMethod(method);

        router.post(
            '/two-factor-choice',
            { method },
            {
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const methods = [
        {
            key: 'webauthn',
            title: 'Use Passkey',
            description: 'Sign in with your fingerprint, face recognition, or device PIN',
            icon: <Fingerprint className="h-6 w-6" />,
            available: availableMethods.includes('webauthn'),
        },
        {
            key: 'totp',
            title: 'Authenticator App',
            description: 'Enter the 6-digit code from your authenticator app',
            icon: <Shield className="h-6 w-6" />,
            available: availableMethods.includes('totp'),
        },
    ];

    return (
        <AuthLayout title="Two-Factor Authentication" description="Choose your authentication method">
            <Head title="Choose Authentication Method" />

            <div className="flex min-h-screen flex-col justify-center py-12 sm:px-6 lg:px-8">
                <div className="sm:mx-auto sm:w-full sm:max-w-md">
                    <h2 className="text-center text-3xl font-bold tracking-tight text-gray-900">Choose Authentication Method</h2>
                    <p className="mt-2 text-center text-sm text-gray-600">Welcome back, {user.name}! How would you like to verify your identity?</p>
                </div>

                <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
                    <div className="space-y-4">
                        {methods
                            .filter((method) => method.available)
                            .map((method) => (
                                <Card
                                    key={method.key}
                                    className={`cursor-pointer transition-colors hover:bg-gray-50 ${
                                        selectedMethod === method.key ? 'ring-2 ring-blue-500' : ''
                                    }`}
                                    onClick={() => !isLoading && handleMethodSelect(method.key)}
                                >
                                    <CardHeader className="pb-2">
                                        <CardTitle className="flex items-center gap-3 text-lg">
                                            {method.icon}
                                            {method.title}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <CardDescription>{method.description}</CardDescription>
                                    </CardContent>
                                </Card>
                            ))}

                        {canUseRecoveryCode && (
                            <div className="border-t pt-4">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => handleMethodSelect('recovery')}
                                    disabled={isLoading}
                                    className="w-full text-sm text-gray-600 hover:text-gray-900"
                                >
                                    <Key className="mr-2 h-4 w-4" />
                                    Use recovery code instead
                                </Button>
                            </div>
                        )}
                    </div>

                    {isLoading && (
                        <div className="mt-4 text-center">
                            <p className="text-sm text-gray-600">Redirecting...</p>
                        </div>
                    )}
                </div>
            </div>
        </AuthLayout>
    );
}
