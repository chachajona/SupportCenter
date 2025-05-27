import WebAuthnManager from '@/components/auth/web-authn-manager';
import AuthLayout from '@/layouts/auth-layout';
import { Head } from '@inertiajs/react';

interface WebAuthnCredential {
    id: string;
    name: string;
    type: string;
    created_at: string;
    last_used_at?: string;
}

interface Props {
    user: {
        id: number;
        name: string;
        email: string;
        webauthn_enabled: boolean;
        preferred_mfa_method: string;
    };
    credentials: WebAuthnCredential[];
}

export default function WebAuthnRegister({ user, credentials }: Props) {
    return (
        <AuthLayout title="Passkey Management" description="Manage your passkeys for secure authentication">
            <Head title="Passkey Management" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <div className="mb-6">
                                <h3 className="text-lg font-medium text-gray-900">Secure Authentication with Passkeys</h3>
                                <p className="mt-1 text-sm text-gray-600">
                                    Passkeys provide a more secure and convenient way to sign in to your account. They use your device's built-in
                                    security features like fingerprint, face recognition, or PIN.
                                </p>
                            </div>

                            <WebAuthnManager user={user} credentials={credentials} />

                            <div className="mt-6 rounded-lg bg-blue-50 p-4">
                                <h4 className="text-sm font-medium text-blue-900">What are Passkeys?</h4>
                                <ul className="mt-2 list-inside list-disc space-y-1 text-sm text-blue-800">
                                    <li>More secure than passwords - they can't be phished or stolen</li>
                                    <li>Faster sign-in with just your fingerprint, face, or device PIN</li>
                                    <li>Work across all your devices when synced</li>
                                    <li>No need to remember complex passwords</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}
