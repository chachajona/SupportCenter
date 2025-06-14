import HeadingSmall from '@/components/heading-small';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useAuthContext } from '@/contexts/AuthContext';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import api from '@/lib/axios';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { AxiosError } from 'axios';
import DOMPurify from 'dompurify';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Two-Factor Authentication',
        href: '/settings/two-factor-authentication',
    },
];

export default function TwoFactorAuthentication() {
    const { user, getUser, loading: authLoading } = useAuthContext();
    const [statusLoading, setStatusLoading] = useState(false);
    const [qrCode, setQrCode] = useState<string | null>(null);
    const [secretKey, setSecretKey] = useState<string | null>(null);
    const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
    const [confirmationCode, setConfirmationCode] = useState('');

    const isTwoFactorEnabled = user?.two_factor_enabled;

    // Check URL parameters to resume 2FA setup after password confirmation
    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const resume2fa = urlParams.get('resume_2fa');

        if (resume2fa === 'setup' && !isTwoFactorEnabled && !qrCode) {
            // Resume 2FA setup after password confirmation
            handleEnableTwoFactor(true);
        } else if (resume2fa === 'show_codes' && isTwoFactorEnabled && !recoveryCodes) {
            // Resume showing recovery codes after password confirmation
            handleShowRecoveryCodes();
        }
    }, [isTwoFactorEnabled, qrCode, recoveryCodes]);

    const handleEnableTwoFactor = async (isResume = false) => {
        setStatusLoading(true);
        try {
            // Step 1: Enable 2FA (generates secret and recovery codes on backend)
            await api.post('/user/two-factor-authentication');

            // Step 2: Fetch the styled QR Code SVG
            const qrResponse = await api.get('/user/two-factor-qr-code');
            // Sanitize the SVG markup before setting it
            const sanitizedQrCode = DOMPurify.sanitize(qrResponse.data, {
                USE_PROFILES: { svg: true, svgFilters: true },
                ADD_TAGS: ['svg', 'path', 'rect', 'circle', 'g', 'defs', 'clipPath', 'use'], // Add common SVG tags
                ADD_ATTR: [
                    'd',
                    'fill',
                    'stroke',
                    'stroke-width',
                    'cx',
                    'cy',
                    'r',
                    'width',
                    'height',
                    'x',
                    'y',
                    'transform',
                    'viewBox',
                    'preserveAspectRatio',
                    'id',
                    'href',
                    'clip-path',
                ],
                FORBID_ATTR: ['style', 'onload', 'onerror'],
            });
            setQrCode(sanitizedQrCode);

            // Step 3: Fetch the secret key for manual entry
            const secretKeyResponse = await api.get('/user/two-factor-secret-key');
            setSecretKey(secretKeyResponse.data.secretKey); // Fortify returns { secretKey: '...' }

            // Step 4: Fetch recovery codes
            // Fortify's recovery code endpoint returns an array of strings directly.
            const recoveryResponse = await api.get('/user/two-factor-recovery-codes');
            setRecoveryCodes(recoveryResponse.data);

            if (!isResume) {
                toast.info('Scan the QR code or use the secret key, then enter the confirmation code.');
            }

            // Clear URL parameters after successful resume
            if (isResume) {
                const url = new URL(window.location.href);
                url.searchParams.delete('resume_2fa');
                window.history.replaceState({}, '', url.toString());
            }
        } catch (error) {
            console.error('Failed to start 2FA enabling process:', error);
            if (error instanceof AxiosError && error.response) {
                if (error.response.data?.message === 'Password confirmation required.' || error.response.status === 423) {
                    toast.info('Password confirmation is required to enable 2FA.');
                    // Add resume parameter to return URL
                    const confirmUrl = route('password.confirm') + '?intended=' + encodeURIComponent(window.location.pathname + '?resume_2fa=setup');
                    router.visit(confirmUrl);
                } else if (
                    error.response.data?.message === 'Two-factor authentication is not pending confirmation.' &&
                    error.response.config.url?.includes('two-factor-qr-code')
                ) {
                    toast.error('2FA setup is already in progress or completed. Please confirm with a code, or disable and re-enable.');
                } else {
                    toast.error('Could not complete 2FA setup. Please try again.');
                }
            } else {
                toast.error('Could not complete 2FA setup. An unexpected error occurred.');
            }
            // Clear partial state if any step failed after the first one
            setQrCode(null);
            setSecretKey(null);
            setRecoveryCodes(null);
        } finally {
            setStatusLoading(false);
        }
    };

    const handleConfirmTwoFactor = async () => {
        if (!confirmationCode) {
            toast.error('Please enter the confirmation code.');
            return;
        }
        setStatusLoading(true);
        try {
            await api.post('/user/confirmed-two-factor-authentication', { code: confirmationCode });
            toast.success('Two-Factor Authentication enabled successfully!');
            await getUser();
            setQrCode(null);
            setSecretKey(null);
            setConfirmationCode('');
            await handleShowRecoveryCodes(true);
        } catch (error) {
            console.error('Failed to confirm 2FA:', error);
            if (
                error instanceof AxiosError &&
                error.response &&
                (error.response.data?.message === 'Password confirmation required.' || error.response.status === 423)
            ) {
                toast.info('Password confirmation is required.');
                const confirmUrl = route('password.confirm') + '?intended=' + encodeURIComponent(window.location.pathname + '?resume_2fa=setup');
                router.visit(confirmUrl);
            } else {
                toast.error('Failed to confirm 2FA. Invalid code or an error occurred.');
            }
        } finally {
            setStatusLoading(false);
        }
    };

    const handleDisableTwoFactor = async () => {
        setStatusLoading(true);
        try {
            await api.delete('/user/two-factor-authentication');
            toast.success('Two-Factor Authentication disabled.');
            await getUser();
            setQrCode(null);
            setSecretKey(null);
            setRecoveryCodes(null);
        } catch (error) {
            console.error('Failed to disable 2FA:', error);
            if (
                error instanceof AxiosError &&
                error.response &&
                (error.response.data?.message === 'Password confirmation required.' || error.response.status === 423)
            ) {
                toast.info('Password confirmation is required to disable 2FA.');
                const confirmUrl = route('password.confirm') + '?intended=' + encodeURIComponent(window.location.pathname);
                router.visit(confirmUrl);
            } else {
                toast.error('Could not disable 2FA. Please try again.');
            }
        } finally {
            setStatusLoading(false);
        }
    };

    const handleShowRecoveryCodes = async (enabledJustNow = false) => {
        setStatusLoading(true);
        try {
            const response = await api.get('/user/two-factor-recovery-codes');
            setRecoveryCodes(response.data);
            if (!enabledJustNow) {
                toast.info('Your recovery codes are shown below.');
            }

            // Clear URL parameters after successful resume
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('resume_2fa') === 'show_codes') {
                const url = new URL(window.location.href);
                url.searchParams.delete('resume_2fa');
                window.history.replaceState({}, '', url.toString());
            }
        } catch (error) {
            console.error('Failed to fetch recovery codes:', error);
            if (
                error instanceof AxiosError &&
                error.response &&
                (error.response.data?.message === 'Password confirmation required.' || error.response.status === 423)
            ) {
                toast.info('Password confirmation is required to view recovery codes.');
                const confirmUrl = route('password.confirm') + '?intended=' + encodeURIComponent(window.location.pathname + '?resume_2fa=show_codes');
                router.visit(confirmUrl);
            } else {
                toast.error('Could not fetch recovery codes.');
            }
        } finally {
            setStatusLoading(false);
        }
    };

    const handleRegenerateRecoveryCodes = async () => {
        setStatusLoading(true);
        try {
            const response = await api.post('/user/two-factor-recovery-codes');
            setRecoveryCodes(response.data);
            toast.success('New recovery codes generated.');
        } catch (error) {
            console.error('Failed to regenerate recovery codes:', error);
            if (
                error instanceof AxiosError &&
                error.response &&
                (error.response.data?.message === 'Password confirmation required.' || error.response.status === 423)
            ) {
                toast.info('Password confirmation is required to regenerate recovery codes.');
                const confirmUrl = route('password.confirm') + '?intended=' + encodeURIComponent(window.location.pathname);
                router.visit(confirmUrl);
            } else {
                toast.error('Could not regenerate recovery codes.');
            }
        } finally {
            setStatusLoading(false);
        }
    };

    if (authLoading) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <SettingsLayout>
                    <Head title="Two-Factor Authentication" />
                    <div className="container mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                        <p>Loading user data...</p>
                    </div>
                </SettingsLayout>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Two-Factor Authentication" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Two-Factor Authentication (2FA)"
                        description="Add an additional layer of security to your account by enabling two-factor authentication."
                    />
                    <Card>
                        <CardContent className="space-y-6">
                            {!isTwoFactorEnabled ? (
                                <div>
                                    <p className="text-muted-foreground mb-4 text-sm">Two-factor authentication is currently disabled.</p>
                                    {!qrCode && (
                                        <Button onClick={() => handleEnableTwoFactor()} disabled={statusLoading}>
                                            {statusLoading ? 'Processing...' : 'Enable Two-Factor Authentication'}
                                        </Button>
                                    )}
                                    {qrCode && secretKey && (
                                        <div className="bg-muted mt-6 space-y-4 rounded-md border p-6">
                                            <CardHeader className="p-0">
                                                <CardTitle>Configure Authenticator App</CardTitle>
                                                <CardDescription>
                                                    Scan the QR code below with your authenticator app (like Google Authenticator, Authy, or Microsoft
                                                    Authenticator). If you cannot scan the QR code, you can manually enter the secret key.
                                                </CardDescription>
                                            </CardHeader>
                                            <div className="flex flex-col items-center space-y-4 md:flex-row md:space-y-0 md:space-x-6">
                                                <div
                                                    dangerouslySetInnerHTML={{ __html: qrCode }}
                                                    className="bg-background rounded-lg border p-2 shadow-sm"
                                                    aria-label="TOTP QR Code"
                                                />
                                                <div>
                                                    <p className="text-center text-sm font-medium md:text-left">Secret Key:</p>
                                                    <pre className="bg-input mt-1 rounded border p-2 font-mono text-xs select-all">{secretKey}</pre>
                                                </div>
                                            </div>
                                            <div className="mt-4 flex flex-col space-y-2">
                                                <Label htmlFor="confirmation-code">Verification Code</Label>
                                                <Input
                                                    type="text"
                                                    id="confirmation-code"
                                                    value={confirmationCode}
                                                    onChange={(e) => setConfirmationCode(e.target.value)}
                                                    placeholder="Enter code from app"
                                                    inputMode="numeric"
                                                    autoComplete="one-time-code"
                                                    className="mt-1 block w-full"
                                                    disabled={statusLoading}
                                                />
                                                <Button
                                                    onClick={handleConfirmTwoFactor}
                                                    disabled={statusLoading || !confirmationCode}
                                                    className="mt-2"
                                                >
                                                    {statusLoading ? 'Confirming...' : 'Confirm & Enable 2FA'}
                                                </Button>
                                            </div>

                                            {recoveryCodes && (
                                                <Alert
                                                    variant="default"
                                                    className="mt-6 border-yellow-400 bg-yellow-50 dark:border-yellow-600 dark:bg-yellow-900/20"
                                                >
                                                    <AlertTitle className="text-yellow-800 dark:text-yellow-200">
                                                        Save Your Recovery Codes!
                                                    </AlertTitle>
                                                    <AlertDescription className="text-yellow-700 dark:text-yellow-300">
                                                        Store these recovery codes in a safe place. They can be used to access your account if you
                                                        lose access to your authenticator app.
                                                        <ul className="mt-2 list-disc space-y-1 pl-5">
                                                            {recoveryCodes.map((code) => (
                                                                <li key={code} className="font-mono text-sm text-yellow-900 dark:text-yellow-100">
                                                                    {code}
                                                                </li>
                                                            ))}
                                                        </ul>
                                                    </AlertDescription>
                                                </Alert>
                                            )}
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div>
                                    <Alert variant="default" className="mb-4 border-green-200 bg-green-50 dark:border-green-700 dark:bg-green-900/20">
                                        <AlertDescription className="text-green-700 dark:text-green-300">
                                            Two-factor authentication is currently enabled on your account.
                                        </AlertDescription>
                                    </Alert>
                                    <div className="space-y-4">
                                        <div>
                                            <h4 className="text-md mb-2 font-semibold">Recovery Codes</h4>
                                            {recoveryCodes ? (
                                                <div className="bg-muted space-y-2 rounded-md border p-4">
                                                    <p className="text-muted-foreground text-sm">
                                                        Store these recovery codes in a safe place. Each code can only be used once.
                                                    </p>
                                                    <Separator className="my-2" />
                                                    <ul className="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
                                                        {recoveryCodes.map((code) => (
                                                            <li key={code} className="font-mono text-sm">
                                                                {code}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                    <Button
                                                        onClick={handleRegenerateRecoveryCodes}
                                                        variant="outline"
                                                        size="sm"
                                                        className="mt-3"
                                                        disabled={statusLoading}
                                                    >
                                                        {statusLoading ? 'Regenerating...' : 'Regenerate Codes'}
                                                    </Button>
                                                </div>
                                            ) : (
                                                <Button onClick={() => handleShowRecoveryCodes()} variant="outline" disabled={statusLoading}>
                                                    {statusLoading ? 'Loading...' : 'Show Recovery Codes'}
                                                </Button>
                                            )}
                                        </div>
                                        <Button onClick={handleDisableTwoFactor} variant="destructive" disabled={statusLoading}>
                                            {statusLoading ? 'Disabling...' : 'Disable Two-Factor Authentication'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
