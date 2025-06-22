import { EmergencyAccessDetailsDialog } from '@/components/rbac/emergency-access-detail-dialog';
import { EmergencyAccessGrantDialog } from '@/components/rbac/emergency-access-grant-dialog';
import { PermissionGate } from '@/components/rbac/permission-gate';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { EmergencyAccess, RBACStats, User } from '@/types/rbac';
import { Head, router } from '@inertiajs/react';
import { Activity, AlertTriangle, Clock, Eye, Plus, Shield, Users, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

interface EmergencyAccessManagementProps {
    emergencyAccesses: EmergencyAccess[];
    stats: RBACStats;
    users: User[];
    available_permissions: Array<{ name: string; display_name?: string; description?: string }>;
}

export default function EmergencyAccessManagement({ emergencyAccesses, stats, users, available_permissions }: EmergencyAccessManagementProps) {
    const availablePermissions = available_permissions;
    const [selectedAccess, setSelectedAccess] = useState<EmergencyAccess | null>(null);
    const [showGrantDialog, setShowGrantDialog] = useState(false);
    const [showDetailsDialog, setShowDetailsDialog] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [realTimeUpdates, setRealTimeUpdates] = useState(true);

    // Real-time updates using polling (in production, use WebSockets)
    useEffect(() => {
        if (!realTimeUpdates) return;

        const interval = setInterval(() => {
            router.reload({ only: ['emergencyAccesses', 'stats'] });
        }, 30000); // Update every 30 seconds

        return () => clearInterval(interval);
    }, [realTimeUpdates]);

    const filteredAccesses = emergencyAccesses.filter((access) => {
        const user = users.find((u) => u.id === access.user_id);
        const matchesSearch =
            user?.name.toLowerCase().includes(searchTerm.toLowerCase()) || user?.email.toLowerCase().includes(searchTerm.toLowerCase());

        const matchesStatus =
            statusFilter === 'all' ||
            (statusFilter === 'active' && access.is_active && new Date(access.expires_at) > new Date()) ||
            (statusFilter === 'expired' && new Date(access.expires_at) <= new Date()) ||
            (statusFilter === 'used' && access.used_at) ||
            (statusFilter === 'revoked' && !access.is_active);

        return matchesSearch && matchesStatus;
    });

    const handleRevokeAccess = (accessId: number) => {
        const reason = prompt('Please provide a reason for revoking this emergency access:');
        if (!reason) return;

        router.patch(
            `/admin/emergency/${accessId}/revoke`,
            { reason },
            {
                onSuccess: () => {
                    // Optionally, show a toast notification here
                },
            },
        );
    };

    const getStatusBadge = (access: EmergencyAccess) => {
        const now = new Date();
        const expiresAt = new Date(access.expires_at);

        if (!access.is_active) {
            return <Badge variant="destructive">Revoked</Badge>;
        }
        if (access.used_at) {
            return <Badge variant="secondary">Used</Badge>;
        }
        if (expiresAt <= now) {
            return <Badge variant="outline">Expired</Badge>;
        }
        return <Badge variant="default">Active</Badge>;
    };

    const getTimeRemaining = (expiresAt: string) => {
        const now = new Date();
        const expires = new Date(expiresAt);
        const diff = expires.getTime() - now.getTime();

        if (diff <= 0) return 'Expired';

        const minutes = Math.floor(diff / (1000 * 60));
        const hours = Math.floor(minutes / 60);

        if (hours > 0) {
            return `${hours}h ${minutes % 60}m remaining`;
        }
        return `${minutes}m remaining`;
    };

    const getProgressPercentage = (grantedAt: string, expiresAt: string) => {
        const granted = new Date(grantedAt).getTime();
        const expires = new Date(expiresAt).getTime();
        const now = new Date().getTime();

        const total = expires - granted;
        const elapsed = now - granted;

        return Math.min(100, Math.max(0, (elapsed / total) * 100));
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Emergency', href: '/admin/emergency' } as BreadcrumbItem]}>
            <Head title="Emergency Access Management" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">Emergency Access Management</h1>
                            <p className="text-muted-foreground">Monitor and manage emergency access permissions</p>
                        </div>

                        <div className="flex items-center gap-4">
                            <Button
                                variant="outline"
                                onClick={() => setRealTimeUpdates(!realTimeUpdates)}
                                className={`gap-2 ${realTimeUpdates ? 'bg-green-50 text-green-700' : ''}`}
                            >
                                <Activity className="h-4 w-4" />
                                {realTimeUpdates ? 'Live Updates On' : 'Live Updates Off'}
                            </Button>

                            <PermissionGate permission="emergency.grant">
                                <Button onClick={() => setShowGrantDialog(true)} className="gap-2">
                                    <Plus className="h-4 w-4" />
                                    Grant Emergency Access
                                </Button>
                            </PermissionGate>
                        </div>
                    </div>

                    {/* Statistics Cards */}
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Active Emergency Access</CardTitle>
                                <AlertTriangle className="h-4 w-4 text-amber-600" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.active_emergency_access}</div>
                                <p className="text-muted-foreground text-xs">Currently active sessions</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total Granted Today</CardTitle>
                                <Shield className="h-4 w-4 text-blue-600" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {emergencyAccesses.filter((a) => new Date(a.granted_at).toDateString() === new Date().toDateString()).length}
                                </div>
                                <p className="text-muted-foreground text-xs">Granted in the last 24h</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Average Duration</CardTitle>
                                <Clock className="h-4 w-4 text-green-600" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">45m</div>
                                <p className="text-muted-foreground text-xs">Average session length</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Users Affected</CardTitle>
                                <Users className="h-4 w-4 text-purple-600" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{new Set(emergencyAccesses.map((a) => a.user_id)).size}</div>
                                <p className="text-muted-foreground text-xs">Unique users with access</p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Security Alert */}
                    {stats.active_emergency_access > 5 && (
                        <Alert>
                            <AlertTriangle className="h-4 w-4" />
                            <AlertDescription>
                                High number of active emergency access sessions detected. Consider reviewing and revoking unnecessary access.
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Filters */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Filter Emergency Access</CardTitle>
                            <CardDescription>Search and filter emergency access records</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-4">
                                <div className="flex-1">
                                    <Input
                                        placeholder="Search by user name or email..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="max-w-sm"
                                    />
                                </div>

                                <Select value={statusFilter} onValueChange={setStatusFilter}>
                                    <SelectTrigger className="w-[180px]">
                                        <SelectValue placeholder="Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Status</SelectItem>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="expired">Expired</SelectItem>
                                        <SelectItem value="used">Used</SelectItem>
                                        <SelectItem value="revoked">Revoked</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Emergency Access List */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Emergency Access Sessions</CardTitle>
                            <CardDescription>Active and recent emergency access grants</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {filteredAccesses.length === 0 ? (
                                    <p className="text-muted-foreground py-8 text-center">No emergency access records found</p>
                                ) : (
                                    filteredAccesses.map((access) => (
                                        <div key={access.id} className="hover:bg-muted/50 flex items-center justify-between rounded-lg border p-4">
                                            <div className="flex-1 space-y-2">
                                                <div className="flex items-center gap-3">
                                                    <div className="font-medium">{users.find((u) => u.id === access.user_id)?.name}</div>
                                                    {getStatusBadge(access)}
                                                    <span className="text-muted-foreground text-sm">{access.permissions.length} permissions</span>
                                                </div>

                                                <div className="text-muted-foreground text-sm">
                                                    <span>Granted by {access.granted_by_user?.name}</span>
                                                    <span className="mx-2">•</span>
                                                    <span>{new Date(access.granted_at).toLocaleString()}</span>
                                                </div>

                                                <div className="text-sm font-medium text-amber-700">Reason: {access.reason}</div>

                                                {access.is_active && new Date(access.expires_at) > new Date() && (
                                                    <div className="space-y-1">
                                                        <div className="flex items-center justify-between text-sm">
                                                            <span className="text-muted-foreground">Time remaining</span>
                                                            <span className="font-medium">{getTimeRemaining(access.expires_at)}</span>
                                                        </div>
                                                        <Progress
                                                            value={getProgressPercentage(access.granted_at, access.expires_at)}
                                                            className="h-2"
                                                        />
                                                    </div>
                                                )}
                                            </div>

                                            <div className="flex items-center gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => {
                                                        setSelectedAccess(access);
                                                        setShowDetailsDialog(true);
                                                    }}
                                                    className="gap-2"
                                                >
                                                    <Eye className="h-4 w-4" />
                                                    Details
                                                </Button>

                                                {access.is_active && (
                                                    <PermissionGate permission="emergency.revoke">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => handleRevokeAccess(access.id)}
                                                            className="text-destructive hover:text-destructive gap-2"
                                                        >
                                                            <XCircle className="h-4 w-4" />
                                                            Revoke
                                                        </Button>
                                                    </PermissionGate>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <EmergencyAccessGrantDialog
                users={users}
                availablePermissions={availablePermissions}
                open={showGrantDialog}
                onClose={() => setShowGrantDialog(false)}
            />

            <EmergencyAccessDetailsDialog emergencyAccess={selectedAccess} open={showDetailsDialog} onClose={() => setShowDetailsDialog(false)} />
        </AppLayout>
    );
}
