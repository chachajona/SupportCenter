import HeadingSmall from '@/components/heading-small';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
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
import { Key, Laptop, Plus, Smartphone, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';

interface WebAuthnCredential {
    id: string;
    name: string;
    type: string;
    created_at: string;
    last_used_at?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Passkeys',
        href: '/settings/webauthn',
    },
];

const handlePasswordConfirmationError = (error: unknown, action: string): boolean => {
    if (error instanceof AxiosError && error.response) {
        if (error.response.data?.message === 'Password confirmation required.' || error.response.status === 423) {
            toast.info(`Password confirmation is required to ${action}.`);
            const confirmUrl = route('password.confirm') + '?intended=' + encodeURIComponent(window.location.pathname);
            router.visit(confirmUrl);
            return true;
        }
    }
    return false;
};

interface WebAuthnStatusProps {
    isEnabled: boolean;
    isLoading: boolean;
    onEnable: () => void;
    onDisable: () => void;
}

function WebAuthnStatus({ isEnabled, isLoading, onEnable, onDisable }: WebAuthnStatusProps) {
    const [showDisableDialog, setShowDisableDialog] = useState(false);

    if (!isEnabled) {
        return (
            <div>
                <p className="text-muted-foreground mb-4 text-sm">Passkeys are currently disabled.</p>
                <Button onClick={onEnable} disabled={isLoading}>
                    {isLoading ? 'Processing...' : 'Enable Passkeys'}
                </Button>
            </div>
        );
    }

    return (
        <div>
            <Alert variant="default" className="mb-4 border-green-200 bg-green-50 dark:border-green-700 dark:bg-green-900/20">
                <AlertDescription className="text-green-700 dark:text-green-300">Passkeys are currently enabled on your account.</AlertDescription>
            </Alert>

            <div className="mt-6">
                <Separator />
                <div className="mt-6">
                    <Dialog open={showDisableDialog} onOpenChange={setShowDisableDialog}>
                        <DialogTrigger asChild>
                            <Button variant="destructive" disabled={isLoading}>
                                {isLoading ? 'Disabling...' : 'Disable Passkeys'}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>Disable Passkeys</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to disable passkeys? All registered devices will be removed. This action cannot be undone.
                            </DialogDescription>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    onClick={() => {
                                        onDisable();
                                        setShowDisableDialog(false);
                                    }}
                                >
                                    Disable Passkeys
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </div>
    );
}

// WebAuthn Credentials List Component
interface WebAuthnCredentialsListProps {
    credentials: WebAuthnCredential[];
    onRemoveCredential: (credentialId: string) => void;
}

function WebAuthnCredentialsList({ credentials, onRemoveCredential }: WebAuthnCredentialsListProps) {
    const [credentialToRemove, setCredentialToRemove] = useState<string | null>(null);

    const getDeviceIcon = (type: string) => {
        switch (type.toLowerCase()) {
            case 'mobile':
                return <Smartphone className="h-4 w-4" />;
            case 'desktop':
                return <Laptop className="h-4 w-4" />;
            default:
                return <Key className="h-4 w-4" />;
        }
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const handleRemoveCredential = () => {
        if (credentialToRemove) {
            onRemoveCredential(credentialToRemove);
            setCredentialToRemove(null);
        }
    };

    return (
        <div>
            <h4 className="text-md mb-2 flex items-center gap-2 font-semibold">
                <Key className="h-4 w-4" />
                Registered Devices
            </h4>

            {credentials.length > 0 ? (
                <div className="bg-muted space-y-3 rounded-md border p-4">
                    <p className="text-muted-foreground text-sm">
                        You have {credentials.length} passkey{credentials.length !== 1 ? 's' : ''} registered.
                    </p>
                    <Separator className="my-2" />
                    <div className="space-y-2">
                        {credentials.map((credential) => (
                            <div key={credential.id} className="bg-background flex items-center justify-between rounded border p-3">
                                <div className="flex items-center gap-3">
                                    {getDeviceIcon(credential.type)}
                                    <div>
                                        <p className="text-sm font-medium">{credential.name}</p>
                                        <p className="text-muted-foreground text-xs">
                                            Added {formatDate(credential.created_at)}
                                            {credential.last_used_at && ` • Last used ${formatDate(credential.last_used_at)}`}
                                        </p>
                                    </div>
                                </div>
                                <Dialog open={credentialToRemove === credential.id} onOpenChange={(open) => !open && setCredentialToRemove(null)}>
                                    <DialogTrigger asChild>
                                        <Button
                                            onClick={() => setCredentialToRemove(credential.id)}
                                            variant="ghost"
                                            size="sm"
                                            className="text-destructive hover:text-destructive"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogTitle>Remove Passkey</DialogTitle>
                                        <DialogDescription>
                                            Are you sure you want to remove this passkey? This action cannot be undone.
                                        </DialogDescription>
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button variant="secondary">Cancel</Button>
                                            </DialogClose>
                                            <Button variant="destructive" onClick={handleRemoveCredential}>
                                                Remove Passkey
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        ))}
                    </div>
                </div>
            ) : (
                <div className="bg-muted rounded-md border p-4">
                    <p className="text-muted-foreground text-sm">No passkeys registered yet.</p>
                </div>
            )}
        </div>
    );
}

// WebAuthn Register Form Component
interface WebAuthnRegisterFormProps {
    isRegistering: boolean;
    isLoading: boolean;
    deviceName: string;
    onDeviceNameChange: (name: string) => void;
    onRegister: () => void;
    onCancel: () => void;
}

function WebAuthnRegisterForm({ isRegistering, isLoading, deviceName, onDeviceNameChange, onRegister, onCancel }: WebAuthnRegisterFormProps) {
    if (!isRegistering) {
        return (
            <Button
                onClick={() => onCancel()}
                variant="outline"
                className="flex items-center gap-2"
                disabled={isLoading}
            >
                <Plus className="h-4 w-4" />
                Add New Passkey
            </Button>
        );
    }

    return (
        <div className="bg-muted mt-6 space-y-4 rounded-md border p-6">
            <CardHeader className="p-0">
                <CardTitle>Register New Passkey</CardTitle>
                <CardDescription>Give your device a name and follow your browser's prompts to set up a new passkey.</CardDescription>
            </CardHeader>
            <div className="flex flex-col space-y-2">
                <Label htmlFor="device-name">Device Name</Label>
                <Input
                    type="text"
                    id="device-name"
                    value={deviceName}
                    onChange={(e) => onDeviceNameChange(e.target.value)}
                    placeholder="e.g., iPhone, MacBook, etc."
                    className="mt-1 block w-full"
                    disabled={isLoading}
                />
                <div className="mt-2 flex gap-2">
                    <Button onClick={onRegister} disabled={isLoading || !deviceName.trim()}>
                        {isLoading ? 'Registering...' : 'Register Device'}
                    </Button>
                    <Button onClick={onCancel} variant="outline" disabled={isLoading}>
                        Cancel
                    </Button>
                </div>
            </div>
        </div>
    );
}

// WebAuthn Info Card Component
function WebAuthnInfoCard() {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Key className="h-5 w-5" />
                    About Passkeys
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                    <h4 className="mb-2 text-sm font-medium text-blue-900 dark:text-blue-200">What are Passkeys?</h4>
                    <ul className="list-inside list-disc space-y-1 text-sm text-blue-800 dark:text-blue-300">
                        <li>More secure than passwords - they can't be phished or stolen</li>
                        <li>Faster sign-in with just your fingerprint, face, or device PIN</li>
                        <li>Work across all your devices when synced to the same platform</li>
                        <li>No need to remember complex passwords</li>
                        <li>Built on industry standards (WebAuthn/FIDO2)</li>
                    </ul>
                </div>
            </CardContent>
        </Card>
    );
}

// Main WebAuthn Component
export default function WebAuthn() {
    const { user, getUser, loading: authLoading } = useAuthContext();
    const [statusLoading, setStatusLoading] = useState(false);
    const [credentials, setCredentials] = useState<WebAuthnCredential[]>([]);
    const [isRegistering, setIsRegistering] = useState(false);
    const [deviceName, setDeviceName] = useState('');

    const isWebAuthnEnabled = user?.webauthn_enabled || false;

    // Check URL parameters to resume WebAuthn setup after password confirmation
    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const resumeWebauthn = urlParams.get('resume_webauthn');

        if (resumeWebauthn === 'setup' && !isWebAuthnEnabled) {
            handleEnableWebAuthn(true);
        } else if (resumeWebauthn === 'register') {
            setIsRegistering(true);
        }
    }, [isWebAuthnEnabled]);

    // Fetch credentials when component mounts or when WebAuthn is enabled
    useEffect(() => {
        if (isWebAuthnEnabled) {
            fetchCredentials();
        }
    }, [isWebAuthnEnabled]);

    const fetchCredentials = async () => {
        try {
            const response = await api.get('/user/webauthn/credentials');
            setCredentials(response.data);
        } catch (error) {
            console.error('Failed to fetch credentials:', error);
        }
    };

    const handleEnableWebAuthn = useCallback(
        async (isResume = false) => {
            setStatusLoading(true);
            try {
                await api.post('/user/webauthn/enable');

                if (!isResume) {
                    toast.success('Passkeys enabled! You can now register your first device.');
                }

                await getUser();
                setIsRegistering(true);

                if (isResume) {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('resume_webauthn');
                    window.history.replaceState({}, '', url.toString());
                }
            } catch (error) {
                console.error('Failed to enable WebAuthn:', error);
                if (!handlePasswordConfirmationError(error, 'enable passkeys')) {
                    toast.error('Could not enable passkeys. An unexpected error occurred.');
                }
            } finally {
                setStatusLoading(false);
            }
        },
        [getUser],
    );

    const handleDisableWebAuthn = async () => {
        setStatusLoading(true);
        try {
            await api.delete('/user/webauthn/disable');
            toast.success('Passkeys disabled successfully.');
            await getUser();
            setCredentials([]);
            setIsRegistering(false);
        } catch (error) {
            console.error('Failed to disable WebAuthn:', error);
            if (!handlePasswordConfirmationError(error, 'disable passkeys')) {
                toast.error('Could not disable passkeys. An unexpected error occurred.');
            }
        } finally {
            setStatusLoading(false);
        }
    };

    const registerNewDevice = async () => {
        if (!deviceName.trim()) {
            toast.error('Please enter a device name.');
            return;
        }

        setStatusLoading(true);
        try {
            const optionsResponse = await api.post('/user/webauthn/register/options');
            const publicKey = optionsResponse.data;

            const credential = (await navigator.credentials.create({
                publicKey: {
                    ...publicKey,
                    challenge: new Uint8Array(Object.values(publicKey.challenge)),
                    user: {
                        ...publicKey.user,
                        id: new Uint8Array(Object.values(publicKey.user.id)),
                    },
                },
            })) as PublicKeyCredential;

            if (!credential) {
                throw new Error('Failed to create credential');
            }

            const credentialData = {
                id: credential.id,
                rawId: Array.from(new Uint8Array(credential.rawId)),
                type: credential.type,
                response: {
                    clientDataJSON: Array.from(new Uint8Array((credential as PublicKeyCredential).response.clientDataJSON)),
                    attestationObject: Array.from(
                        new Uint8Array(((credential as PublicKeyCredential).response as AuthenticatorAttestationResponse).attestationObject),
                    ),
                },
                name: deviceName,
            };

            await api.post('/user/webauthn/register', credentialData);

            toast.success('Device registered successfully!');
            setDeviceName('');
            setIsRegistering(false);
            await fetchCredentials();
        } catch (error) {
            console.error('Failed to register device:', error);
            if (!handlePasswordConfirmationError(error, 'register a device')) {
                if (error instanceof AxiosError && error.response?.status === 403) {
                    toast.error('Registration cancelled or not allowed.');
                } else {
                    toast.error('Failed to register device. Please ensure your browser supports passkeys.');
                }
            }
        } finally {
            setStatusLoading(false);
        }
    };

    const removeCredential = async (credentialId: string) => {
        try {
            await api.delete(`/user/webauthn/${credentialId}`);
            toast.success('Passkey removed successfully!');
            await fetchCredentials();

            if (credentials.length === 1) {
                await getUser();
            }
        } catch (error) {
            console.error('Failed to remove credential:', error);
            if (!handlePasswordConfirmationError(error, 'remove a passkey')) {
                toast.error('Could not remove passkey. An unexpected error occurred.');
            }
        }
    };

    if (authLoading) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <SettingsLayout>
                    <Head title="Passkeys" />
                    <div className="container mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                        <p>Loading user data...</p>
                    </div>
                </SettingsLayout>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Passkeys" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Passkeys"
                        description="Log in with your fingerprint, face recognition or a PIN instead of a password. Passkeys can be synced across devices logged into the same platform (like Apple ID or a Google account)."
                    />

                    <Card>
                        <CardContent className="space-y-6">
                            <WebAuthnStatus
                                isEnabled={isWebAuthnEnabled}
                                isLoading={statusLoading}
                                onEnable={() => handleEnableWebAuthn()}
                                onDisable={handleDisableWebAuthn}
                            />

                            {isWebAuthnEnabled && (
                                <div className="space-y-4">
                                    <WebAuthnCredentialsList credentials={credentials} onRemoveCredential={removeCredential} />

                                    <WebAuthnRegisterForm
                                        isRegistering={isRegistering}
                                        isLoading={statusLoading}
                                        deviceName={deviceName}
                                        onDeviceNameChange={setDeviceName}
                                        onRegister={registerNewDevice}
                                        onCancel={() => setIsRegistering(!isRegistering)}
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <WebAuthnInfoCard />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
