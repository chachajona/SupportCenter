import { PermissionEditDialog } from '@/components/rbac/permission-edit-dialog';
import { PermissionGate } from '@/components/rbac/permission-gate';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Permission } from '@/types/rbac';
import { Head, router } from '@inertiajs/react';
import { Edit, Eye, Plus, Search, Settings, Shield, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

interface PermissionManagementProps {
    permissions: Permission[];
    stats: {
        total_permissions: number;
        active_permissions: number;
        total_resources: number;
        total_roles_using_permissions: number;
    };
}

export default function PermissionManagement({ permissions, stats }: PermissionManagementProps) {
    const [selectedPermission, setSelectedPermission] = useState<Permission | null>(null);
    const [showEditDialog, setShowEditDialog] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [resourceFilter, setResourceFilter] = useState<string>('all');
    const [statusFilter, setStatusFilter] = useState<string>('all');

    // Group permissions by resource is handled in filteredPermissionsByResource

    const resources = useMemo(() => {
        return [...new Set(permissions.map((p) => p.resource))].sort();
    }, [permissions]);

    const filteredPermissions = useMemo(() => {
        return permissions.filter((permission) => {
            const matchesSearch =
                permission.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                permission.display_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                permission.resource.toLowerCase().includes(searchTerm.toLowerCase()) ||
                permission.action.toLowerCase().includes(searchTerm.toLowerCase());

            const matchesResource = resourceFilter === 'all' || permission.resource === resourceFilter;

            const matchesStatus =
                statusFilter === 'all' ||
                (statusFilter === 'active' && permission.is_active) ||
                (statusFilter === 'inactive' && !permission.is_active);

            return matchesSearch && matchesResource && matchesStatus;
        });
    }, [permissions, searchTerm, resourceFilter, statusFilter]);

    const filteredPermissionsByResource = useMemo(() => {
        return filteredPermissions.reduce(
            (acc, permission) => {
                if (!acc[permission.resource]) {
                    acc[permission.resource] = [];
                }
                acc[permission.resource].push(permission);
                return acc;
            },
            {} as Record<string, Permission[]>,
        );
    }, [filteredPermissions]);

    const handleDeletePermission = (permission: Permission) => {
        if (confirm(`Are you sure you want to delete the permission "${permission.display_name}"? This action cannot be undone.`)) {
            router.delete(`/admin/permissions/${permission.id}`, {
                onSuccess: () => {
                    // Success notification would be handled by the backend
                },
                onError: (errors) => {
                    console.error('Failed to delete permission:', errors);
                },
            });
        }
    };

    const getActionColor = (action: string) => {
        const colors: Record<string, string> = {
            create: 'bg-green-100 text-green-800',
            read: 'bg-blue-100 text-blue-800',
            view: 'bg-blue-100 text-blue-800',
            edit: 'bg-yellow-100 text-yellow-800',
            update: 'bg-yellow-100 text-yellow-800',
            delete: 'bg-red-100 text-red-800',
            manage: 'bg-purple-100 text-purple-800',
            assign: 'bg-orange-100 text-orange-800',
            approve: 'bg-indigo-100 text-indigo-800',
        };
        return colors[action.toLowerCase()] || 'bg-gray-100 text-gray-800';
    };

    const getResourceIcon = (resource: string) => {
        switch (resource.toLowerCase()) {
            case 'tickets':
                return '🎫';
            case 'users':
                return '👥';
            case 'roles':
                return '🔐';
            case 'permissions':
                return '⚡';
            case 'departments':
                return '🏢';
            case 'settings':
                return '⚙️';
            case 'reports':
                return '📊';
            case 'system':
                return '🖥️';
            default:
                return '📋';
        }
    };

    return (
        <>
            <Head title="Permission Management" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="space-y-6">
                    {/* Header */}
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">Permission Management</h1>
                            <p className="text-muted-foreground">Manage system permissions and their assignments</p>
                        </div>

                        <PermissionGate permission="permissions.create">
                            <Button onClick={() => setShowEditDialog(true)} className="gap-2">
                                <Plus className="h-4 w-4" />
                                Create Permission
                            </Button>
                        </PermissionGate>
                    </div>

                    {/* Stats Cards */}
                    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total Permissions</CardTitle>
                                <Shield className="text-muted-foreground h-4 w-4" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.total_permissions}</div>
                                <p className="text-muted-foreground text-xs">{stats.active_permissions} active</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Resources</CardTitle>
                                <Settings className="text-muted-foreground h-4 w-4" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.total_resources}</div>
                                <p className="text-muted-foreground text-xs">different resources</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Roles Using</CardTitle>
                                <Shield className="text-muted-foreground h-4 w-4" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.total_roles_using_permissions}</div>
                                <p className="text-muted-foreground text-xs">roles have permissions</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Avg per Resource</CardTitle>
                                <Shield className="text-muted-foreground h-4 w-4" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {stats.total_resources > 0 ? Math.round(stats.total_permissions / stats.total_resources) : 0}
                                </div>
                                <p className="text-muted-foreground text-xs">permissions per resource</p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Filters */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Filters</CardTitle>
                            <CardDescription>Filter permissions by name, resource, or status</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-4">
                                <div className="flex-1">
                                    <div className="relative">
                                        <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                        <Input
                                            placeholder="Search permissions..."
                                            value={searchTerm}
                                            onChange={(e) => setSearchTerm(e.target.value)}
                                            className="pl-9"
                                        />
                                    </div>
                                </div>

                                <Select value={resourceFilter} onValueChange={setResourceFilter}>
                                    <SelectTrigger className="w-[180px]">
                                        <SelectValue placeholder="Resource" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Resources</SelectItem>
                                        {resources.map((resource) => (
                                            <SelectItem key={resource} value={resource}>
                                                {getResourceIcon(resource)} {resource}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <Select value={statusFilter} onValueChange={setStatusFilter}>
                                    <SelectTrigger className="w-[150px]">
                                        <SelectValue placeholder="Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Status</SelectItem>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>

                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setSearchTerm('');
                                        setResourceFilter('all');
                                        setStatusFilter('all');
                                    }}
                                >
                                    Clear Filters
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Permissions by Resource */}
                    <div className="space-y-6">
                        {Object.entries(filteredPermissionsByResource).map(([resource, resourcePermissions]) => (
                            <Card key={resource}>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            <span className="text-2xl">{getResourceIcon(resource)}</span>
                                            <div>
                                                <CardTitle className="capitalize">{resource} Permissions</CardTitle>
                                                <CardDescription>
                                                    {resourcePermissions.length} permissions for {resource} resource
                                                </CardDescription>
                                            </div>
                                        </div>
                                        <Badge variant="outline">
                                            {resourcePermissions.filter((p) => p.is_active).length}/{resourcePermissions.length} active
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                        {resourcePermissions.map((permission) => (
                                            <Card key={permission.id} className={`${!permission.is_active ? 'opacity-60' : ''}`}>
                                                <CardHeader className="pb-3">
                                                    <div className="flex items-start justify-between">
                                                        <div className="flex-1">
                                                            <div className="mb-1 flex items-center gap-2">
                                                                <CardTitle className="text-sm">{permission.display_name}</CardTitle>
                                                                {!permission.is_active && (
                                                                    <Badge variant="secondary" className="text-xs">
                                                                        Inactive
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            <div className="flex items-center gap-2">
                                                                <Badge className={getActionColor(permission.action)} variant="secondary">
                                                                    {permission.action}
                                                                </Badge>
                                                                <Badge variant="outline" className="text-xs">
                                                                    {permission.name}
                                                                </Badge>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    {permission.description && (
                                                        <CardDescription className="line-clamp-2 text-xs">{permission.description}</CardDescription>
                                                    )}
                                                </CardHeader>

                                                <CardContent className="pt-0">
                                                    <div className="text-muted-foreground mb-3 flex items-center gap-2 text-xs">
                                                        <span>Used by {permission.roles?.length || 0} roles</span>
                                                    </div>

                                                    <div className="flex items-center gap-2">
                                                        <PermissionGate permission="permissions.view">
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => router.visit(`/admin/permissions/${permission.id}`)}
                                                                className="flex-1 gap-2"
                                                            >
                                                                <Eye className="h-3 w-3" />
                                                                View
                                                            </Button>
                                                        </PermissionGate>

                                                        <PermissionGate permission="permissions.edit">
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => {
                                                                    setSelectedPermission(permission);
                                                                    setShowEditDialog(true);
                                                                }}
                                                                className="flex-1 gap-2"
                                                            >
                                                                <Edit className="h-3 w-3" />
                                                                Edit
                                                            </Button>
                                                        </PermissionGate>

                                                        <PermissionGate permission="permissions.delete">
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                className="text-destructive hover:text-destructive"
                                                                onClick={() => handleDeletePermission(permission)}
                                                            >
                                                                <Trash2 className="h-3 w-3" />
                                                            </Button>
                                                        </PermissionGate>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    {filteredPermissions.length === 0 && (
                        <Card>
                            <CardContent className="flex flex-col items-center justify-center py-12">
                                <Shield className="text-muted-foreground mb-4 h-12 w-12" />
                                <h3 className="mb-2 text-lg font-semibold">No permissions found</h3>
                                <p className="text-muted-foreground mb-4 text-center">
                                    {searchTerm || resourceFilter !== 'all' || statusFilter !== 'all'
                                        ? 'No permissions match your current filters. Try adjusting your search criteria.'
                                        : 'There are no permissions in the system yet.'}
                                </p>
                                {searchTerm || resourceFilter !== 'all' || statusFilter !== 'all' ? (
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            setSearchTerm('');
                                            setResourceFilter('all');
                                            setStatusFilter('all');
                                        }}
                                    >
                                        Clear Filters
                                    </Button>
                                ) : (
                                    <PermissionGate permission="permissions.create">
                                        <Button onClick={() => setShowEditDialog(true)} className="gap-2">
                                            <Plus className="h-4 w-4" />
                                            Create First Permission
                                        </Button>
                                    </PermissionGate>
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>

            {/* Edit Dialog */}
            <PermissionEditDialog
                permission={selectedPermission}
                open={showEditDialog}
                onClose={() => {
                    setShowEditDialog(false);
                    setSelectedPermission(null);
                }}
            />
        </>
    );
}
