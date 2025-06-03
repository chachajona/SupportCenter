import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useAuthContext } from '@/contexts/AuthContext';
import AuthLayout from '@/layouts/auth-layout';
import { useState } from 'react';
import { toast } from 'sonner';

interface TwoFactorChallengeForm {
    code: string;
}

export default function TwoFactorChallenge() {
    const [isRecoveryCode, setIsRecoveryCode] = useState(false);
    const { confirmTwoFactor, cancelTwoFactor, error, loading } = useAuthContext();

    const { data, setData, processing, errors, reset } = useForm<Required<TwoFactorChallengeForm>>({
        code: '',
    });

    const submit: FormEventHandler = async (e) => {
        e.preventDefault();

        if (!data.code.trim()) {
            toast.error('Please enter a code.');
            return;
        }

        try {
            const success = await confirmTwoFactor(data.code.trim());

            if (!success && error) {
                // Show the error from context, but also provide specific feedback
                if (error.toLowerCase().includes('unauthenticated')) {
                    toast.error('Session expired', {
                        description: 'Please log in again.',
                    });
                } else if (error.toLowerCase().includes('invalid') || error.toLowerCase().includes('incorrect')) {
                    toast.error('Invalid code', {
                        description: 'Please check your authenticator app and try again.',
                    });
                } else {
                    toast.error('Authentication failed', {
                        description: error,
                    });
                }
            }

            if (success) {
                toast.success('Welcome back!');
            }
        } catch (err) {
            console.error('2FA submission error:', err);
            toast.error('An unexpected error occurred. Please try again.');
        }
    };

    const handleRecoveryToggle = () => {
        setIsRecoveryCode(!isRecoveryCode);
        setData('code', '');
        reset('code');
    };

    const handleCancel = async () => {
        try {
            await cancelTwoFactor();
            toast.info('Login cancelled. Please sign in again.');
        } catch (err) {
            console.error('Cancel 2FA error:', err);
            toast.error('Failed to cancel. Please try again.');
        }
    };

    return (
        <AuthLayout
            title="Two-Factor Authentication"
            description={isRecoveryCode ? 'Enter one of your recovery codes.' : 'Enter the code from your authenticator app.'}
        >
            <Head title="2FA Challenge" />

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="code">{isRecoveryCode ? 'Recovery Code' : 'Authentication Code'}</Label>
                        <Input
                            id="code"
                            name="code"
                            type="text"
                            inputMode={isRecoveryCode ? 'text' : 'numeric'}
                            autoComplete="one-time-code"
                            required
                            autoFocus
                            tabIndex={1}
                            placeholder={isRecoveryCode ? 'Enter recovery code' : 'Enter 2FA code'}
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            disabled={loading || processing}
                            className="text-center"
                        />
                        <InputError message={error || errors.code} />
                    </div>

                    <Button type="submit" disabled={loading || processing || !data.code.trim()} className="w-full" tabIndex={2}>
                        {(loading || processing) && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {loading || processing ? 'Verifying...' : 'Verify Code'}
                    </Button>
                </div>

                <Separator />

                <div className="space-y-4">
                    <div className="text-center">
                        <button
                            type="button"
                            onClick={handleRecoveryToggle}
                            className="text-muted-foreground hover:text-foreground text-sm underline"
                            disabled={loading || processing}
                            tabIndex={3}
                        >
                            {isRecoveryCode ? 'Use authenticator code instead' : 'Use a recovery code instead'}
                        </button>
                    </div>

                    <div className="text-muted-foreground text-center text-sm">
                        <TextLink href="#" onClick={handleCancel} disabled={loading || processing} tabIndex={4}>
                            Cancel and return to login
                        </TextLink>
                    </div>
                </div>
            </form>
        </AuthLayout>
    );
}
