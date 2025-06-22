import { LoadingSkeleton } from '@/components/rbac/loading-skeleton';
import { RouteGuard } from '@/components/rbac/route-guard';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { format } from 'date-fns';
import { CalendarDays, Clock, Shield, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Permission {
    id: number;
    name: string;
    display_name: string;
    description?: string;
    resource: string;
}

interface Role {
    id: number;
    name: string;
    display_name: string;
    description?: string;
    hierarchy_level: number;
    is_active: boolean;
    is_system: boolean;
    created_at: string;
    updated_at: string;
    permissions: Permission[];
    users_count: number;
}

interface AuditLogEntry {
    id: number;
    action: string;
    user_name: string;
    created_at: string;
    description?: string;
}

interface Props {
    role: Role;
    recentAudits: AuditLogEntry[];
}

export default function Show({ role, recentAudits }: Props) {
    const [loading, setLoading] = useState(true);

    // Simulate initial loading for first visit
    useEffect(() => {
        const timer = setTimeout(() => setLoading(false), 300);
        return () => clearTimeout(timer);
    }, []);

    return (
        <RouteGuard permissions={['roles.view']}>
            <AppLayout
                breadcrumbs={[
                    { title: 'Roles', href: '/admin/roles' },
                    { title: role.display_name, href: `/admin/roles/${role.id}` },
                ]}
            >
                <Head title={`Role: ${role.display_name}`} />

                <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                    {loading ? (
                        <LoadingSkeleton type="dashboard" label="Loading role details" className="space-y-6" />
                    ) : (
                        <div className="space-y-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h1 className="text-2xl font-bold tracking-tight">{role.display_name}</h1>
                                    <p className="text-muted-foreground">Role details and permissions</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge variant={role.is_active ? 'default' : 'secondary'}>{role.is_active ? 'Active' : 'Inactive'}</Badge>
                                    {role.is_system && <Badge variant="outline">System Role</Badge>}
                                </div>
                            </div>

                            <div className="grid gap-6 md:grid-cols-2">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <Shield className="h-5 w-5" />
                                            Role Information
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div>
                                            <div className="text-muted-foreground text-sm font-medium">Name</div>
                                            <div className="text-sm">{role.name}</div>
                                        </div>
                                        <div>
                                            <div className="text-muted-foreground text-sm font-medium">Display Name</div>
                                            <div className="text-sm">{role.display_name}</div>
                                        </div>
                                        {role.description && (
                                            <div>
                                                <div className="text-muted-foreground text-sm font-medium">Description</div>
                                                <div className="text-sm">{role.description}</div>
                                            </div>
                                        )}
                                        <div>
                                            <div className="text-muted-foreground text-sm font-medium">Hierarchy Level</div>
                                            <div className="text-sm">{role.hierarchy_level}</div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <Users className="h-5 w-5" />
                                            Usage Statistics
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div>
                                            <div className="text-muted-foreground text-sm font-medium">Assigned Users</div>
                                            <div className="text-2xl font-bold">{role.users_count}</div>
                                        </div>
                                        <div>
                                            <div className="text-muted-foreground text-sm font-medium">Permissions</div>
                                            <div className="text-2xl font-bold">{role.permissions.length}</div>
                                        </div>
                                        <div className="text-muted-foreground flex items-center gap-2 text-sm">
                                            <CalendarDays className="h-4 w-4" />
                                            Created {format(new Date(role.created_at), 'PPP')}
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <div className="grid gap-6 md:grid-cols-2">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Permissions</CardTitle>
                                        <CardDescription>Permissions granted to this role</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {role.permissions.length > 0 ? (
                                            <div className="space-y-2">
                                                {role.permissions.map((permission) => (
                                                    <div key={permission.id} className="flex items-center justify-between rounded-md border p-2">
                                                        <div>
                                                            <div className="text-sm font-medium">{permission.display_name}</div>
                                                            <div className="text-muted-foreground text-xs">{permission.name}</div>
                                                        </div>
                                                        <Badge variant="secondary">{permission.resource}</Badge>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="text-muted-foreground text-sm">No permissions assigned</p>
                                        )}
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <Clock className="h-5 w-5" />
                                            Recent Activity
                                        </CardTitle>
                                        <CardDescription>Recent audit log entries for this role</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {recentAudits.length > 0 ? (
                                            <div className="space-y-2">
                                                {recentAudits.map((audit) => (
                                                    <div key={audit.id} className="flex items-center justify-between rounded-md border p-2">
                                                        <div>
                                                            <div className="text-sm font-medium capitalize">{audit.action}</div>
                                                            <div className="text-muted-foreground text-xs">by {audit.user_name}</div>
                                                        </div>
                                                        <div className="text-muted-foreground text-xs">
                                                            {format(new Date(audit.created_at), 'PPp')}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="text-muted-foreground text-sm">No recent activity</p>
                                        )}
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    )}
                </div>
            </AppLayout>
        </RouteGuard>
    );
}
