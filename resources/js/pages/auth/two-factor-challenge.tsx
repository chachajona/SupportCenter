import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuthContext } from '@/contexts/AuthContext';
import React, { useState } from 'react';

export function TwoFactorChallenge() {
    const [code, setCode] = useState('');
    const { confirmTwoFactor, error, loading, setTwoFactorRequired } = useAuthContext();

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!code.trim()) {
            // Optionally set a local error for empty code
            return;
        }
        await confirmTwoFactor(code);
        // If successful, useAuth hook handles redirect.
        // If there's an error, it's in `error` from context and can be displayed.
    };

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-gray-100 p-4">
            <Card className="w-full max-w-md">
                <CardHeader>
                    <CardTitle className="text-center">Two-Factor Authentication</CardTitle>
                    <CardDescription className="text-center">Enter the code from your authenticator app.</CardDescription>
                </CardHeader>
                <form onSubmit={handleSubmit}>
                    <CardContent className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="otp-code" className="sr-only">
                                One-Time Password
                            </Label>
                            <Input
                                id="otp-code"
                                name="code"
                                type="text"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                required
                                placeholder="Enter 2FA Code"
                                value={code}
                                onChange={(e) => setCode(e.target.value)}
                                disabled={loading}
                                aria-describedby={error ? 'otp-error' : undefined}
                            />
                        </div>

                        {error && (
                            <p id="otp-error" className="text-center text-sm text-red-600" role="alert">
                                {error}
                            </p>
                        )}
                    </CardContent>
                    <CardFooter className="flex flex-col space-y-4">
                        <Button type="submit" disabled={loading} className="w-full">
                            {loading ? 'Verifying...' : 'Verify Code'}
                        </Button>
                        <Button type="button" variant="ghost" onClick={() => setTwoFactorRequired(false)} disabled={loading}>
                            Cancel
                        </Button>
                    </CardFooter>
                </form>
            </Card>
        </div>
    );
}
