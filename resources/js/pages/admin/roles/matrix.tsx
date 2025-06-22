import { LoadingSkeleton } from '@/components/rbac/loading-skeleton';
import { RouteGuard } from '@/components/rbac/route-guard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Save, Search, Shield, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface Permission {
    id: number;
    name: string;
    display_name: string;
    description?: string;
    resource: string;
    is_active: boolean;
}

interface Role {
    id: number;
    name: string;
    display_name: string;
    description?: string;
    hierarchy_level: number;
    is_active: boolean;
    is_system: boolean;
}

interface MatrixEntry {
    role_id: number;
    permission_id: number;
    granted: boolean;
}

interface Props {
    roles: Role[];
    permissions: Permission[];
    matrix: MatrixEntry[];
}

export default function Matrix({ roles, permissions, matrix }: Props) {
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedResource, setSelectedResource] = useState('all');
    const [changes, setChanges] = useState<Record<string, boolean>>({});
    const [saving, setSaving] = useState(false);
    const [loading, setLoading] = useState(true);

    // Initialize loading state
    useEffect(() => {
        const timer = setTimeout(() => setLoading(false), 400);
        return () => clearTimeout(timer);
    }, []);

    // Get unique resources for filtering
    const resources = ['all', ...Array.from(new Set(permissions.map((p) => p.resource)))];

    // Filter permissions based on search and resource
    const filteredPermissions = permissions.filter((permission) => {
        const matchesSearch =
            permission.display_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            permission.name.toLowerCase().includes(searchTerm.toLowerCase());
        const matchesResource = selectedResource === 'all' || permission.resource === selectedResource;
        return matchesSearch && matchesResource;
    });

    // Helper function to check if role has permission
    const hasPermission = (roleId: number, permissionId: number): boolean => {
        const key = `${roleId}-${permissionId}`;
        if (Object.hasOwn(changes, key)) {
            return changes[key];
        }
        return matrix.some((entry) => entry.role_id === roleId && entry.permission_id === permissionId && entry.granted);
    };

    // Handle permission toggle
    const togglePermission = (roleId: number, permissionId: number) => {
        const key = `${roleId}-${permissionId}`;
        const currentValue = hasPermission(roleId, permissionId);
        setChanges((prev) => ({
            ...prev,
            [key]: !currentValue,
        }));
    };

    // Save changes
    const saveChanges = async () => {
        setSaving(true);
        try {
            const updates = Object.entries(changes).map(([key, granted]) => {
                const [roleId, permissionId] = key.split('-').map(Number);
                return { role_id: roleId, permission_id: permissionId, granted };
            });

            if (updates.length === 0) return;

            await router.patch(
                '/admin/roles/matrix/update',
                {
                    changes: updates,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        setChanges({});
                        toast.success('Permission matrix updated successfully', {
                            description: 'All role permissions have been saved',
                        });
                    },
                    onError: () => {
                        toast.error('Failed to update permission matrix', {
                            description: 'Please try again or check your network connection',
                        });
                    },
                },
            );
        } catch (error) {
            console.error('Failed to save matrix:', error);
        } finally {
            setSaving(false);
        }
    };

    const hasChanges = Object.keys(changes).length > 0;

    if (loading) {
        return (
            <RouteGuard permissions={['roles.view_matrix']}>
                <AppLayout
                    breadcrumbs={[
                        { title: 'Roles', href: '/admin/roles' },
                        { title: 'Permission Matrix', href: '/admin/roles/matrix' },
                    ]}
                >
                    <Head title="Permission Matrix" />
                    <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                        <LoadingSkeleton type="table" count={10} label="Loading permission matrix" className="space-y-4" />
                    </div>
                </AppLayout>
            </RouteGuard>
        );
    }

    return (
        <RouteGuard permissions={['roles.view_matrix']}>
            <AppLayout
                breadcrumbs={[
                    { title: 'Roles', href: '/admin/roles' },
                    { title: 'Permission Matrix', href: '/admin/roles/matrix' },
                ]}
            >
                <Head title="Permission Matrix" />

                <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                    <div className="space-y-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight">Permission Matrix</h1>
                                <p className="text-muted-foreground">Manage role permissions across your system</p>
                            </div>
                            {hasChanges && (
                                <Button onClick={saveChanges} disabled={saving}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {saving ? 'Updating...' : `Update (${Object.keys(changes).length})`}
                                </Button>
                            )}
                        </div>

                        <div className="flex items-center gap-4">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground absolute top-3 left-3 h-4 w-4" />
                                <Input
                                    placeholder="Search permissions..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <select
                                value={selectedResource}
                                onChange={(e) => setSelectedResource(e.target.value)}
                                className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            >
                                {resources.map((resource) => (
                                    <option key={resource} value={resource}>
                                        {resource === 'all' ? 'All Resources' : resource}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Shield className="h-5 w-5" />
                                    Permission Matrix
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="w-[300px]">Permission</TableHead>
                                                {roles
                                                    .filter((role) => role.is_active)
                                                    .map((role) => (
                                                        <TableHead key={role.id} className="min-w-[120px] text-center">
                                                            <div className="space-y-1">
                                                                <div className="font-medium">{role.display_name}</div>
                                                                <div className="flex items-center justify-center gap-1">
                                                                    <Badge variant="outline" className="text-xs">
                                                                        Level {role.hierarchy_level}
                                                                    </Badge>
                                                                    {role.is_system && (
                                                                        <Badge variant="secondary" className="text-xs">
                                                                            System
                                                                        </Badge>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </TableHead>
                                                    ))}
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {filteredPermissions.map((permission) => (
                                                <TableRow key={permission.id}>
                                                    <TableCell>
                                                        <div className="space-y-1">
                                                            <div className="font-medium">{permission.display_name}</div>
                                                            <div className="text-muted-foreground text-sm">{permission.name}</div>
                                                            <Badge variant="outline" className="text-xs">
                                                                {permission.resource}
                                                            </Badge>
                                                        </div>
                                                    </TableCell>
                                                    {roles
                                                        .filter((role) => role.is_active)
                                                        .map((role) => (
                                                            <TableCell key={role.id} className="text-center">
                                                                <Switch
                                                                    checked={hasPermission(role.id, permission.id)}
                                                                    onCheckedChange={() => togglePermission(role.id, permission.id)}
                                                                    disabled={role.is_system && role.name === 'system_administrator'}
                                                                />
                                                            </TableCell>
                                                        ))}
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>

                                {filteredPermissions.length === 0 && (
                                    <div className="py-8 text-center">
                                        <p className="text-muted-foreground">No permissions found matching your criteria</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <div className="grid gap-4 md:grid-cols-3">
                            <Card>
                                <CardContent className="pt-6">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium">Total Roles</p>
                                            <p className="text-2xl font-bold">{roles.filter((r) => r.is_active).length}</p>
                                        </div>
                                        <Users className="text-muted-foreground h-8 w-8" />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent className="pt-6">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium">Total Permissions</p>
                                            <p className="text-2xl font-bold">{permissions.filter((p) => p.is_active).length}</p>
                                        </div>
                                        <Shield className="text-muted-foreground h-8 w-8" />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent className="pt-6">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium">Resources</p>
                                            <p className="text-2xl font-bold">{resources.length - 1}</p>
                                        </div>
                                        <Badge className="h-8 w-8" />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </AppLayout>
        </RouteGuard>
    );
}
