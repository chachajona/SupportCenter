import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuthContext } from '@/contexts/AuthContext';
import { LoaderCircle } from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';

interface LoginFormProps {
    onLoginSuccess?: () => void;
    canResetPassword?: boolean;
}

export function LoginForm({ onLoginSuccess, canResetPassword = true }: LoginFormProps) {
    const { login, loading, error } = useAuthContext();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        try {
            const success = await login({ email, password, remember });

            if (success) {
                if (onLoginSuccess) {
                    onLoginSuccess();
                }
            } else if (error) {
                toast.error('Login failed', {
                    description: error,
                });
            }
        } catch (err) {
            toast.error('Login failed', {
                description: 'An unexpected error occurred. Please try again.',
            });
            console.error('Login error:', err);
        }
    };

    return (
        <form className="flex flex-col gap-6" onSubmit={handleSubmit}>
            <div className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autoFocus
                        tabIndex={1}
                        autoComplete="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        placeholder="email@example.com"
                        aria-required="true"
                    />
                </div>

                <div className="grid gap-2">
                    <div className="flex items-center">
                        <Label htmlFor="password">Password</Label>
                        {canResetPassword && (
                            <TextLink href={route('password.request')} className="ml-auto text-sm" tabIndex={5}>
                                Forgot password?
                            </TextLink>
                        )}
                    </div>
                    <Input
                        id="password"
                        type="password"
                        required
                        tabIndex={2}
                        autoComplete="current-password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        placeholder="Password"
                        aria-required="true"
                    />
                </div>

                <div className="flex items-center space-x-3">
                    <Checkbox
                        id="remember"
                        name="remember"
                        checked={remember}
                        onCheckedChange={(checked) => setRemember(checked === true)}
                        tabIndex={3}
                    />
                    <Label htmlFor="remember">Remember me</Label>
                </div>

                {/* We can still show the inline error for accessibility, but it will be duplicated in the toast */}
                {error && (
                    <div className="rounded border border-red-400 bg-red-100 p-4 text-sm text-red-700" role="alert">
                        {error}
                    </div>
                )}

                <Button type="submit" className="mt-4 w-full" tabIndex={4} disabled={loading}>
                    {loading && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                    Log in
                </Button>
            </div>

            <div className="text-muted-foreground text-center text-sm">
                Don't have an account?{' '}
                <TextLink href={route('register')} tabIndex={5}>
                    Sign up
                </TextLink>
            </div>
        </form>
    );
}
