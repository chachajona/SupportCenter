import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle, LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';
import { toast } from 'sonner';

interface Props {
    status?: string;
}

export default function EmergencyAccess({ status }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        reason: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post('/emergency-access', {
            onSuccess: () => {
                toast.success('Emergency access request submitted. Check your email for further instructions.');
                reset();
            },
            onError: () => {
                toast.error('Failed to submit emergency access request. Please check your credentials and try again.');
            },
        });
    };

    return (
        <AuthLayout title="Emergency Access" description="Request emergency access to your account">
            <Head title="Emergency Access" />

            <div className="flex min-h-screen flex-col justify-center py-12 sm:px-6 lg:px-8">
                <div className="sm:mx-auto sm:w-full sm:max-w-md">
                    <div className="flex justify-center">
                        <AlertTriangle className="h-12 w-12 text-amber-500" />
                    </div>
                    <h2 className="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900">Emergency Access</h2>
                    <p className="mt-2 text-center text-sm text-gray-600">
                        If you're unable to access your account through normal authentication methods, you can request emergency access.
                    </p>
                </div>

                <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
                    {status && (
                        <div className="mb-4 rounded-md bg-green-50 p-4">
                            <div className="text-sm text-green-700">{status}</div>
                        </div>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Request Emergency Access</CardTitle>
                            <CardDescription>
                                Please provide your credentials and explain why you need emergency access. An email will be sent to your registered
                                email address.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form className="space-y-6" onSubmit={submit}>
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email Address</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        required
                                        autoFocus
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="your@email.com"
                                        disabled={processing}
                                    />
                                    {errors.email && <div className="text-sm text-red-600">{errors.email}</div>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="password">Password</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        required
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="Your account password"
                                        disabled={processing}
                                    />
                                    {errors.password && <div className="text-sm text-red-600">{errors.password}</div>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="reason">Reason for Emergency Access</Label>
                                    <Textarea
                                        id="reason"
                                        required
                                        value={data.reason}
                                        onChange={(e) => setData('reason', e.target.value)}
                                        placeholder="Please explain why you need emergency access (e.g., lost device, broken authenticator app, etc.)"
                                        rows={4}
                                        disabled={processing}
                                        maxLength={500}
                                    />
                                    <div className="text-xs text-gray-500">{data.reason.length}/500 characters</div>
                                    {errors.reason && <div className="text-sm text-red-600">{errors.reason}</div>}
                                </div>

                                <Button type="submit" className="w-full" disabled={processing}>
                                    {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                    {processing ? 'Submitting Request...' : 'Request Emergency Access'}
                                </Button>
                            </form>

                            <div className="mt-6 border-t pt-6">
                                <div className="text-center">
                                    <p className="text-sm text-gray-600">
                                        Remember your authentication method?{' '}
                                        <a href="/login" className="font-medium text-blue-600 hover:text-blue-500">
                                            Return to login
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="mt-6">
                        <div className="rounded-md bg-amber-50 p-4">
                            <div className="flex">
                                <AlertTriangle className="h-5 w-5 text-amber-400" />
                                <div className="ml-3">
                                    <h3 className="text-sm font-medium text-amber-800">Important Security Notice</h3>
                                    <div className="mt-2 text-sm text-amber-700">
                                        <ul className="list-disc space-y-1 pl-5">
                                            <li>Emergency access links expire in 24 hours</li>
                                            <li>Only use this if you cannot access your account normally</li>
                                            <li>Contact support if you suspect unauthorized access</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}
