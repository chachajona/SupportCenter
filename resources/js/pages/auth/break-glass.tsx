import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Shield } from 'lucide-react';
import React, { useState } from 'react';

export default function BreakGlass() {
    const [token, setToken] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError('');

        try {
            const response = await fetch('/break-glass', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ token }),
            });

            const data = await response.json();

            if (data.success) {
                window.location.href = data.redirect || '/dashboard';
            } else {
                setError(data.message || 'Invalid break-glass token');
            }
        } catch (err) {
            console.error(err);
            setError('Network error occurred');
        } finally {
            setLoading(false);
        }
    };

    return (
        <>
            <Head title="Emergency Break-Glass Access" />

            <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-red-50 to-orange-100 p-4">
                <Card className="w-full max-w-md border-red-200">
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                            <Shield className="h-6 w-6 text-red-600" />
                        </div>
                        <CardTitle className="text-2xl font-bold text-red-900">Emergency Access</CardTitle>
                        <CardDescription className="text-red-700">Enter your break-glass token to gain emergency access</CardDescription>
                    </CardHeader>

                    <CardContent>
                        <Alert className="mb-6 border-orange-200 bg-orange-50">
                            <AlertTriangle className="h-4 w-4 text-orange-600" />
                            <AlertDescription className="text-orange-800">
                                This is for emergency situations only. All break-glass access is logged and audited.
                            </AlertDescription>
                        </Alert>

                        {error && (
                            <Alert variant="destructive" className="mb-4">
                                <AlertDescription>{error}</AlertDescription>
                            </Alert>
                        )}

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <Label htmlFor="token">Break-Glass Token</Label>
                                <Input
                                    id="token"
                                    type="text"
                                    value={token}
                                    onChange={(e) => setToken(e.target.value)}
                                    placeholder="Enter emergency access token"
                                    required
                                    className="font-mono"
                                />
                            </div>

                            <Button type="submit" disabled={loading || !token.trim()} className="w-full bg-red-600 hover:bg-red-700">
                                {loading ? 'Authenticating...' : 'Emergency Login'}
                            </Button>
                        </form>

                        <div className="mt-6 text-center">
                            <Button variant="link" onClick={() => router.visit('/login')} className="text-gray-600">
                                Back to Normal Login
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
