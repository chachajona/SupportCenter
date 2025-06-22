import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Permission, Role } from '@/types/rbac';
import { router } from '@inertiajs/react';
import { Layers, Save, Search } from 'lucide-react';
import React, { lazy, Suspense, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

// Lazy load the permission presets component for code splitting
const PermissionPresets = lazy(() => import('./permission-presets'));

interface RoleEditDialogProps {
    role: Role | null;
    permissions: Permission[];
    open: boolean;
    onClose: () => void;
}

export function RoleEditDialog({ role, permissions, open, onClose }: RoleEditDialogProps) {
    const [formData, setFormData] = useState({
        name: '',
        display_name: '',
        description: '',
        hierarchy_level: 1,
        is_active: true,
        selected_permissions: [] as number[],
    });
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [searchTerm, setSearchTerm] = useState('');
    const [resourceFilter, setResourceFilter] = useState<string>('all');

    const isEditing = !!role;

    // Group permissions by resource
    const permissionsByResource = useMemo(() => {
        return permissions.reduce(
            (acc, permission) => {
                if (!acc[permission.resource]) {
                    acc[permission.resource] = [];
                }
                acc[permission.resource].push(permission);
                return acc;
            },
            {} as Record<string, Permission[]>,
        );
    }, [permissions]);

    const filteredPermissions = useMemo(() => {
        let filtered = permissions;

        if (searchTerm) {
            filtered = filtered.filter(
                (perm) =>
                    perm.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    perm.display_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    perm.resource.toLowerCase().includes(searchTerm.toLowerCase()),
            );
        }

        if (resourceFilter !== 'all') {
            filtered = filtered.filter((perm) => perm.resource === resourceFilter);
        }

        return filtered;
    }, [permissions, searchTerm, resourceFilter]);

    const resources = useMemo(() => {
        return [...new Set(permissions.map((p) => p.resource))].sort();
    }, [permissions]);

    useEffect(() => {
        if (role) {
            setFormData({
                name: role.name,
                display_name: role.display_name,
                description: role.description || '',
                hierarchy_level: role.hierarchy_level,
                is_active: role.is_active,
                selected_permissions: role.permissions?.map((p) => p.id) || [],
            });
        } else {
            setFormData({
                name: '',
                display_name: '',
                description: '',
                hierarchy_level: 1,
                is_active: true,
                selected_permissions: [],
            });
        }
        setErrors({});
        setSearchTerm('');
        setResourceFilter('all');
    }, [role, open]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        const url = isEditing ? `/admin/roles/${role.id}` : '/admin/roles';
        const method = isEditing ? 'patch' : 'post';

        const { selected_permissions, ...otherData } = formData;
        const payload = {
            ...otherData,
            permissions: selected_permissions,
        };

        router[method](url, payload, {
            onSuccess: () => {
                toast.success(
                    isEditing ? `Role "${formData.display_name}" updated successfully` : `Role "${formData.display_name}" created successfully`,
                    {
                        description: isEditing ? 'Role permissions and settings have been updated' : 'The new role is now available for assignment',
                    },
                );
                onClose();
            },
            onError: (errors) => {
                toast.error(isEditing ? 'Failed to update role' : 'Failed to create role', {
                    description: 'Please check the form for errors and try again.',
                });
                setErrors(errors as unknown as Record<string, string[]>);
            },
            onFinish: () => {
                setLoading(false);
            },
        });
    };

    const handlePermissionToggle = (permissionId: number) => {
        setFormData((prev) => ({
            ...prev,
            selected_permissions: prev.selected_permissions.includes(permissionId)
                ? prev.selected_permissions.filter((id) => id !== permissionId)
                : [...prev.selected_permissions, permissionId],
        }));
    };

    const handleSelectAllPermissionsForResource = (resource: string) => {
        const resourcePermissions = permissionsByResource[resource] || [];
        const resourcePermissionIds = resourcePermissions.map((p) => p.id);
        const allSelected = resourcePermissionIds.every((id) => formData.selected_permissions.includes(id));

        if (allSelected) {
            // Deselect all permissions for this resource
            setFormData((prev) => ({
                ...prev,
                selected_permissions: prev.selected_permissions.filter((id) => !resourcePermissionIds.includes(id)),
            }));
        } else {
            // Select all permissions for this resource
            const newPermissions = [...new Set([...formData.selected_permissions, ...resourcePermissionIds])];
            setFormData((prev) => ({
                ...prev,
                selected_permissions: newPermissions,
            }));
        }
    };

    const getResourceSelectionStatus = (resource: string) => {
        const resourcePermissions = permissionsByResource[resource] || [];
        const selectedCount = resourcePermissions.filter((p) => formData.selected_permissions.includes(p.id)).length;
        const totalCount = resourcePermissions.length;

        if (selectedCount === 0) return 'none';
        if (selectedCount === totalCount) return 'all';
        return 'partial';
    };

    const handleApplyPreset = (newSelectedPermissions: number[]) => {
        setFormData((prev) => ({
            ...prev,
            selected_permissions: newSelectedPermissions,
        }));
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="flex h-[95vh] max-w-[90vw] flex-col lg:max-w-7xl">
                <DialogHeader>
                    <DialogTitle>{isEditing ? 'Edit Role' : 'Create New Role'}</DialogTitle>
                    <DialogDescription>
                        {isEditing ? 'Update the role details and permissions.' : 'Create a new role and assign permissions.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-1 flex-col overflow-hidden">
                    <div className="space-y-6 p-1">
                        {/* Basic Information */}
                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="name">Name *</Label>
                                <Input
                                    id="name"
                                    value={formData.name}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    placeholder="support_agent"
                                    className={errors.name ? 'border-red-500' : ''}
                                />
                                {errors.name && <p className="text-sm text-red-500">{errors.name[0]}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="display_name">Display Name *</Label>
                                <Input
                                    id="display_name"
                                    value={formData.display_name}
                                    onChange={(e) => setFormData({ ...formData, display_name: e.target.value })}
                                    placeholder="Support Agent"
                                    className={errors.display_name ? 'border-red-500' : ''}
                                />
                                {errors.display_name && <p className="text-sm text-red-500">{errors.display_name[0]}</p>}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={formData.description}
                                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                placeholder="Brief description of this role..."
                                rows={2}
                                className={errors.description ? 'border-red-500' : ''}
                            />
                            {errors.description && <p className="text-sm text-red-500">{errors.description[0]}</p>}
                        </div>

                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="hierarchy_level">Hierarchy Level</Label>
                                <Select
                                    value={formData.hierarchy_level.toString()}
                                    onValueChange={(value) => setFormData({ ...formData, hierarchy_level: parseInt(value) })}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1">Level 1 (Lowest)</SelectItem>
                                        <SelectItem value="2">Level 2</SelectItem>
                                        <SelectItem value="3">Level 3</SelectItem>
                                        <SelectItem value="4">Level 4</SelectItem>
                                        <SelectItem value="5">Level 5</SelectItem>
                                        <SelectItem value="6">Level 6</SelectItem>
                                        <SelectItem value="7">Level 7</SelectItem>
                                        <SelectItem value="8">Level 8</SelectItem>
                                        <SelectItem value="9">Level 9</SelectItem>
                                        <SelectItem value="10">Level 10 (Highest)</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.hierarchy_level && <p className="text-sm text-red-500">{errors.hierarchy_level[0]}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label>Status</Label>
                                <div className="flex h-10 items-center space-x-2">
                                    <Checkbox
                                        id="is_active"
                                        checked={formData.is_active}
                                        onCheckedChange={(checked) => setFormData({ ...formData, is_active: !!checked })}
                                    />
                                    <Label htmlFor="is_active">Active</Label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Permissions Section with Tabs */}
                    <Card className="flex-1 overflow-hidden">
                        <CardHeader className="pb-4">
                            <CardTitle className="flex items-center justify-between">
                                <span>Permissions ({formData.selected_permissions.length} selected)</span>
                                <Badge variant="outline">{permissions.length} total</Badge>
                            </CardTitle>
                            <CardDescription>Select the permissions this role should have</CardDescription>
                        </CardHeader>
                        <CardContent className="flex h-full flex-col overflow-hidden">
                            <Tabs defaultValue="presets" className="flex flex-1 flex-col overflow-hidden">
                                <TabsList className="mb-4 grid w-full grid-cols-2">
                                    <TabsTrigger value="presets" className="gap-2">
                                        <Layers className="h-4 w-4" />
                                        Quick Presets
                                    </TabsTrigger>
                                    <TabsTrigger value="manual" className="gap-2">
                                        <Search className="h-4 w-4" />
                                        Manual Selection
                                    </TabsTrigger>
                                </TabsList>

                                <TabsContent value="presets" className="flex-1 overflow-hidden data-[state=active]:flex data-[state=active]:flex-col">
                                    <div className="flex-1 overflow-hidden">
                                        <Suspense
                                            fallback={
                                                <div className="text-muted-foreground flex h-40 items-center justify-center">
                                                    Loading permission presets...
                                                </div>
                                            }
                                        >
                                            <PermissionPresets
                                                permissions={permissions}
                                                selectedPermissions={formData.selected_permissions}
                                                onApplyPreset={handleApplyPreset}
                                                className="h-full"
                                            />
                                        </Suspense>
                                    </div>
                                </TabsContent>

                                <TabsContent value="manual" className="flex-1 overflow-hidden data-[state=active]:flex data-[state=active]:flex-col">
                                    <div className="flex h-full flex-col overflow-hidden">
                                        {/* Permission Filters */}
                                        <div className="mb-4 flex flex-shrink-0 items-center gap-4">
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
                                                <SelectTrigger className="w-[200px]">
                                                    <SelectValue placeholder="Filter by resource" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">All Resources</SelectItem>
                                                    {resources.map((resource) => (
                                                        <SelectItem key={resource} value={resource}>
                                                            {resource}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        {/* Permissions by Resource - Scrollable */}
                                        <div className="flex-1 space-y-4 overflow-y-auto pr-2">
                                            {Object.entries(permissionsByResource).map(([resource, resourcePermissions]) => {
                                                const filteredResourcePermissions = resourcePermissions.filter((perm) =>
                                                    filteredPermissions.includes(perm),
                                                );

                                                if (filteredResourcePermissions.length === 0) return null;

                                                const selectionStatus = getResourceSelectionStatus(resource);

                                                return (
                                                    <div key={resource} className="rounded-lg border p-4">
                                                        <div className="mb-3 flex items-center justify-between">
                                                            <div className="flex items-center gap-2">
                                                                <Checkbox
                                                                    checked={selectionStatus === 'all'}
                                                                    onCheckedChange={() => handleSelectAllPermissionsForResource(resource)}
                                                                    ref={(el) => {
                                                                        const input = el?.querySelector('input');
                                                                        if (input) input.indeterminate = selectionStatus === 'partial';
                                                                    }}
                                                                />
                                                                <h4 className="font-medium capitalize">{resource}</h4>
                                                                <Badge variant="outline" className="ml-2">
                                                                    {
                                                                        filteredResourcePermissions.filter((p) =>
                                                                            formData.selected_permissions.includes(p.id),
                                                                        ).length
                                                                    }
                                                                    /{filteredResourcePermissions.length}
                                                                </Badge>
                                                            </div>
                                                        </div>

                                                        <div className="grid grid-cols-1 gap-2">
                                                            {filteredResourcePermissions.map((permission) => (
                                                                <div
                                                                    key={permission.id}
                                                                    className="hover:bg-muted flex items-center space-x-2 rounded p-2"
                                                                >
                                                                    <Checkbox
                                                                        checked={formData.selected_permissions.includes(permission.id)}
                                                                        onCheckedChange={() => handlePermissionToggle(permission.id)}
                                                                    />
                                                                    <div className="flex-1">
                                                                        <div className="text-sm font-medium">{permission.display_name}</div>
                                                                        <div className="text-muted-foreground text-xs">{permission.name}</div>
                                                                        {permission.description && (
                                                                            <div className="text-muted-foreground mt-1 text-xs">
                                                                                {permission.description}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                );
                                            })}

                                            {filteredPermissions.length === 0 && (
                                                <div className="text-muted-foreground py-8 text-center">
                                                    No permissions match your search criteria.
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </TabsContent>
                            </Tabs>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="mt-4 flex flex-shrink-0 justify-end gap-4 border-t pt-4">
                        <Button type="button" variant="outline" onClick={onClose} disabled={loading}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={loading} className="gap-2">
                            <Save className="h-4 w-4" />
                            {loading ? 'Saving...' : isEditing ? 'Update Role' : 'Create Role'}
                        </Button>
                    </div>

                    {/* Generic error message */}
                    {errors.message && (
                        <div className="bg-destructive/20 text-destructive mb-4 rounded p-3 text-sm">
                            {Array.isArray(errors.message) ? errors.message[0] : (errors.message as unknown as string)}
                        </div>
                    )}
                </form>
            </DialogContent>
        </Dialog>
    );
}
