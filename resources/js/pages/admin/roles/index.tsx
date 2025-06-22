import { RoleCardsSkeleton } from '@/components/rbac/loading-skeleton';
import { PermissionGate } from '@/components/rbac/permission-gate';
import { PermissionMatrix } from '@/components/rbac/permission-matrix';
import { RoleEditDialog } from '@/components/rbac/role-edit-dialog';
import { RoleHierarchyView } from '@/components/rbac/role-hierarchy-view';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Permission, Role } from '@/types/rbac';
import { Head, router } from '@inertiajs/react';
import { Edit, Eye, Plus, Search, Shield, Trash2, Users } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface RoleManagementProps {
    roles: Role[];
    permissions: Permission[];
    stats: {
        total_roles: number;
        active_roles: number;
        total_permissions: number;
        total_users_with_roles: number;
    };
}

export default function RoleManagement({ roles, permissions, stats }: RoleManagementProps) {
    const [selectedRole, setSelectedRole] = useState<Role | null>(null);
    const [showEditDialog, setShowEditDialog] = useState(false);
    const [showMatrix, setShowMatrix] = useState(false);
    const [showHierarchy, setShowHierarchy] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [hierarchyFilter, setHierarchyFilter] = useState<string>('all');
    const [loading, setLoading] = useState(true);

    // Simulate initial loading for first visit
    useEffect(() => {
        const timer = setTimeout(() => setLoading(false), 500);
        return () => clearTimeout(timer);
    }, []);

    const filteredRoles = useMemo(() => {
        return roles.filter((role) => {
            const matchesSearch =
                role.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                role.display_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                role.description?.toLowerCase().includes(searchTerm.toLowerCase());

            const matchesStatus =
                statusFilter === 'all' || (statusFilter === 'active' && role.is_active) || (statusFilter === 'inactive' && !role.is_active);

            const matchesHierarchy = hierarchyFilter === 'all' || role.hierarchy_level.toString() === hierarchyFilter;

            return matchesSearch && matchesStatus && matchesHierarchy;
        });
    }, [roles, searchTerm, statusFilter, hierarchyFilter]);

    const handleDeleteRole = (role: Role) => {
        if (confirm(`Are you sure you want to delete the role "${role.display_name}"? This action cannot be undone.`)) {
            router.delete(`/admin/roles/${role.id}`, {
                onSuccess: () => {
                    toast.success(`Role "${role.display_name}" deleted successfully`, {
                        description: 'The role has been permanently removed from the system',
                    });
                },
                onError: (errors) => {
                    toast.error(`Failed to delete role "${role.display_name}"`, {
                        description: 'The role may be in use or you may not have sufficient permissions',
                    });
                    console.error('Failed to delete role:', errors);
                },
            });
        }
    };

    const getHierarchyLevelColor = (level: number) => {
        const colors = [
            'bg-blue-100 text-blue-800',
            'bg-green-100 text-green-800',
            'bg-yellow-100 text-yellow-800',
            'bg-purple-100 text-purple-800',
            'bg-red-100 text-red-800',
        ];
        return colors[level % colors.length];
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Roles', href: '/admin/roles' } as BreadcrumbItem]}>
            <Head title="Role Management" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="space-y-6">
                    {/* Header */}
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">Role Management</h1>
                            <p className="text-muted-foreground">Manage system roles and their permissions</p>
                        </div>

                        <div className="flex items-center gap-4">
                            <PermissionGate permission="roles.view_hierarchy">
                                <Button variant="outline" onClick={() => setShowHierarchy(true)} className="gap-2">
                                    <Eye className="h-4 w-4" />
                                    Hierarchy View
                                </Button>
                            </PermissionGate>

                            <PermissionGate permission="roles.view_matrix">
                                <Button variant="outline" onClick={() => setShowMatrix(true)} className="gap-2">
                                    <Shield className="h-4 w-4" />
                                    Permission Matrix
                                </Button>
                            </PermissionGate>

                            <PermissionGate permission="roles.create">
                                <Button onClick={() => setShowEditDialog(true)} className="gap-2">
                                    <Plus className="h-4 w-4" />
                                    Create Role
                                </Button>
                            </PermissionGate>
                        </div>
                    </div>

                    {loading ? (
                        <RoleCardsSkeleton />
                    ) : (
                        <>
                            {/* Stats Cards */}
                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <CardTitle className="text-sm font-medium">Total Roles</CardTitle>
                                        <Shield className="text-muted-foreground h-4 w-4" />
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{stats.total_roles}</div>
                                        <p className="text-muted-foreground text-xs">{stats.active_roles} active</p>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <CardTitle className="text-sm font-medium">Total Permissions</CardTitle>
                                        <Shield className="text-muted-foreground h-4 w-4" />
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{stats.total_permissions}</div>
                                        <p className="text-muted-foreground text-xs">Available permissions</p>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <CardTitle className="text-sm font-medium">Users with Roles</CardTitle>
                                        <Users className="text-muted-foreground h-4 w-4" />
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{stats.total_users_with_roles}</div>
                                        <p className="text-muted-foreground text-xs">Have assigned roles</p>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <CardTitle className="text-sm font-medium">Average Permissions</CardTitle>
                                        <Shield className="text-muted-foreground h-4 w-4" />
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">
                                            {stats.total_roles > 0 ? Math.round(permissions.length / stats.total_roles) : 0}
                                        </div>
                                        <p className="text-muted-foreground text-xs">Per role</p>
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Filters */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Filters</CardTitle>
                                    <CardDescription>Filter roles by name, status, or hierarchy level</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center gap-4">
                                        <div className="flex-1">
                                            <div className="relative">
                                                <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                                <Input
                                                    placeholder="Search roles..."
                                                    value={searchTerm}
                                                    onChange={(e) => setSearchTerm(e.target.value)}
                                                    className="pl-9"
                                                />
                                            </div>
                                        </div>

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

                                        <Select value={hierarchyFilter} onValueChange={setHierarchyFilter}>
                                            <SelectTrigger className="w-[150px]">
                                                <SelectValue placeholder="Level" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All Levels</SelectItem>
                                                <SelectItem value="1">Level 1</SelectItem>
                                                <SelectItem value="2">Level 2</SelectItem>
                                                <SelectItem value="3">Level 3</SelectItem>
                                                <SelectItem value="4">Level 4</SelectItem>
                                            </SelectContent>
                                        </Select>

                                        <Button
                                            variant="outline"
                                            onClick={() => {
                                                setSearchTerm('');
                                                setStatusFilter('all');
                                                setHierarchyFilter('all');
                                            }}
                                        >
                                            Clear Filters
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Roles Grid */}
                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                                {filteredRoles.map((role) => (
                                    <Card key={role.id} className="relative">
                                        <CardHeader className="pb-3">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <CardTitle className="text-lg">{role.display_name}</CardTitle>
                                                    {!role.is_active && <Badge variant="secondary">Inactive</Badge>}
                                                </div>
                                                <Badge className={getHierarchyLevelColor(role.hierarchy_level)}>Level {role.hierarchy_level}</Badge>
                                            </div>
                                            <CardDescription className="line-clamp-2">{role.description}</CardDescription>
                                        </CardHeader>

                                        <CardContent className="space-y-4">
                                            <div className="text-muted-foreground flex items-center gap-4 text-sm">
                                                <div className="flex items-center gap-1">
                                                    <Users className="h-4 w-4" />
                                                    {role.users_count || 0} users
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <Shield className="h-4 w-4" />
                                                    {role.permissions?.length || 0} permissions
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-2">
                                                <PermissionGate permission="roles.view">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => router.visit(`/admin/roles/${role.id}`)}
                                                        className="flex-1 gap-2"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                        View
                                                    </Button>
                                                </PermissionGate>

                                                <PermissionGate permission="roles.edit">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => {
                                                            setSelectedRole(role);
                                                            setShowEditDialog(true);
                                                        }}
                                                        className="flex-1 gap-2"
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                        Edit
                                                    </Button>
                                                </PermissionGate>

                                                <PermissionGate permission="roles.delete">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => handleDeleteRole(role)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </PermissionGate>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>

                            {filteredRoles.length === 0 && (
                                <Card>
                                    <CardContent className="flex flex-col items-center justify-center py-12">
                                        <Shield className="text-muted-foreground mb-4 h-12 w-12" />
                                        <h3 className="mb-2 text-lg font-semibold">No roles found</h3>
                                        <p className="text-muted-foreground mb-4 text-center">
                                            {searchTerm || statusFilter !== 'all' || hierarchyFilter !== 'all'
                                                ? 'No roles match your current filters. Try adjusting your search criteria.'
                                                : 'There are no roles in the system yet.'}
                                        </p>
                                        {searchTerm || statusFilter !== 'all' || hierarchyFilter !== 'all' ? (
                                            <Button
                                                variant="outline"
                                                onClick={() => {
                                                    setSearchTerm('');
                                                    setStatusFilter('all');
                                                    setHierarchyFilter('all');
                                                }}
                                            >
                                                Clear Filters
                                            </Button>
                                        ) : (
                                            <PermissionGate permission="roles.create">
                                                <Button onClick={() => setShowEditDialog(true)} className="gap-2">
                                                    <Plus className="h-4 w-4" />
                                                    Create First Role
                                                </Button>
                                            </PermissionGate>
                                        )}
                                    </CardContent>
                                </Card>
                            )}
                        </>
                    )}
                </div>
            </div>

            {/* Dialogs */}
            <RoleEditDialog
                role={selectedRole}
                permissions={permissions}
                open={showEditDialog}
                onClose={() => {
                    setShowEditDialog(false);
                    setSelectedRole(null);
                }}
            />

            <PermissionMatrix roles={roles} permissions={permissions} open={showMatrix} onClose={() => setShowMatrix(false)} />

            <RoleHierarchyView open={showHierarchy} onClose={() => setShowHierarchy(false)} roles={roles} />
        </AppLayout>
    );
}
