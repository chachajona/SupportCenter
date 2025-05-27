import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import React, { useState } from 'react';
import { toast } from 'sonner';

interface WebAuthnCredential {
    id: string;
    name: string;
    type: string;
    created_at: string;
    last_used_at?: string;
}

interface WebAuthnManagerProps {
    credentials: WebAuthnCredential[];
    onCredentialsUpdate?: (credentials: WebAuthnCredential[]) => void;
}

const WebAuthnManager: React.FC<WebAuthnManagerProps> = ({ credentials, onCredentialsUpdate }) => {
    const [isRegistering, setIsRegistering] = useState(false);
    const [deviceName, setDeviceName] = useState('');
    const [credentialToRemove, setCredentialToRemove] = useState<string | null>(null);

    const registerNewDevice = async () => {
        if (!deviceName.trim()) return;

        setIsRegistering(true);

        try {
            // Get registration options
            const optionsResponse = await fetch('/user/webauthn/register/options', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (!optionsResponse.ok) {
                throw new Error('Failed to get registration options');
            }

            const publicKey = await optionsResponse.json();

            // Use WebAuthn API
            if (navigator.credentials && navigator.credentials.create) {
                const credential = (await navigator.credentials.create({ publicKey })) as PublicKeyCredential;

                if (credential) {
                    // Send the credential to the server
                    const registerResponse = await fetch('/user/webauthn/register', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        },
                        body: JSON.stringify({
                            name: deviceName,
                            id: credential.id,
                            rawId: Array.from(new Uint8Array(credential.rawId)),
                            response: {
                                clientDataJSON: Array.from(new Uint8Array(credential.response.clientDataJSON)),
                                attestationObject: Array.from(
                                    new Uint8Array((credential.response as AuthenticatorAttestationResponse).attestationObject),
                                ),
                            },
                            type: credential.type,
                        }),
                    });

                    if (registerResponse.ok) {
                        const newCredential = await registerResponse.json();
                        toast.success('Passkey added successfully!');
                        setDeviceName('');

                        // Update credentials list via callback instead of reloading
                        if (onCredentialsUpdate) {
                            onCredentialsUpdate([...credentials, newCredential]);
                        }
                    } else {
                        throw new Error('Failed to register credential');
                    }
                }
            } else {
                throw new Error('WebAuthn not supported');
            }
        } catch (error) {
            console.error('WebAuthn registration failed:', error);
            toast.error('Failed to register passkey. Please try again.');
        } finally {
            setIsRegistering(false);
        }
    };

    const handleRemoveCredential = async () => {
        if (!credentialToRemove) return;

        try {
            const response = await fetch(`/user/webauthn/${credentialToRemove}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (response.ok) {
                toast.success('Passkey removed successfully!');

                // Update credentials list via callback instead of reloading
                if (onCredentialsUpdate) {
                    const updatedCredentials = credentials.filter((cred) => cred.id !== credentialToRemove);
                    onCredentialsUpdate(updatedCredentials);
                }
            } else {
                throw new Error('Failed to remove credential');
            }
        } catch (error) {
            console.error('Failed to remove credential:', error);
            toast.error('Failed to remove passkey. Please try again.');
        } finally {
            setCredentialToRemove(null);
        }
    };

    return (
        <div className="rounded-lg bg-white p-6 shadow">
            <h3 className="mb-4 text-lg font-medium text-gray-900">Passkey Management</h3>

            {credentials.length === 0 ? (
                <p className="text-gray-500">No passkeys registered yet.</p>
            ) : (
                <div className="mb-4 space-y-3">
                    {credentials.map((credential) => (
                        <div key={credential.id} className="flex items-center justify-between rounded-lg border p-3">
                            <div>
                                <p className="font-medium">{credential.name}</p>
                                <p className="text-sm text-gray-500">
                                    Added {new Date(credential.created_at).toLocaleDateString()}
                                    {credential.last_used_at && <> • Last used {new Date(credential.last_used_at).toLocaleDateString()}</>}
                                </p>
                            </div>
                            <Dialog open={credentialToRemove === credential.id} onOpenChange={(open) => !open && setCredentialToRemove(null)}>
                                <DialogTrigger asChild>
                                    <button onClick={() => setCredentialToRemove(credential.id)} className="text-red-600 hover:text-red-800">
                                        Remove
                                    </button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>Remove Passkey</DialogTitle>
                                    <DialogDescription>Are you sure you want to remove this passkey? This action cannot be undone.</DialogDescription>
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
            )}

            <div className="border-t pt-4">
                <div className="flex gap-2">
                    <input
                        type="text"
                        placeholder="Device name (e.g., iPhone, Laptop)"
                        value={deviceName}
                        onChange={(e) => setDeviceName(e.target.value)}
                        className="flex-1 rounded-md border px-3 py-2"
                    />
                    <button
                        onClick={registerNewDevice}
                        disabled={isRegistering || !deviceName.trim()}
                        className="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {isRegistering ? 'Adding...' : 'Add Passkey'}
                    </button>
                </div>
                <p className="mt-2 text-sm text-gray-500">
                    Passkeys use your device's built-in security (fingerprint, face recognition, or PIN) for secure authentication.
                </p>
            </div>
        </div>
    );
};

export default WebAuthnManager;
