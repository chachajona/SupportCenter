import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useRBAC } from '@/contexts/RBACContext';
import { Permission, Role } from '@/types/rbac';
import { AlertCircle, Check, Download, Edit, Eye, Filter, Loader2, Minus, Search } from 'lucide-react';
import React, { useMemo, useState } from 'react';

interface PermissionMatrixProps {
    roles: Role[];
    permissions: Permission[];
    open: boolean;
    onClose: () => void;
}

export function PermissionMatrix({ roles, permissions, open, onClose }: PermissionMatrixProps) {
    const { hasPermission: userHasPermission } = useRBAC();
    const [searchTerm, setSearchTerm] = useState('');
    const [resourceFilter, setResourceFilter] = useState('all');
    const [roleFilter, setRoleFilter] = useState('all');
    const [showOnlyAssigned, setShowOnlyAssigned] = useState(false);
    const [editMode, setEditMode] = useState(false);
    const [loadingCells, setLoadingCells] = useState<Set<string>>(new Set());
    const [error, setError] = useState<string | null>(null);

    // Check if user can edit the matrix
    const canEditMatrix = userHasPermission('roles.edit_matrix');

    // Check if a role has a specific permission
    const hasRolePermission = (role: Role, permission: Permission): boolean => {
        return role.permissions?.some((p) => p.id === permission.id) || false;
    };

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

    const resources = useMemo(() => {
        return [...new Set(permissions.map((p) => p.resource))].sort();
    }, [permissions]);

    // Filter permissions based on search and filters
    const filteredPermissions = useMemo(() => {
        return permissions.filter((permission) => {
            const matchesSearch =
                permission.display_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                permission.name.toLowerCase().includes(searchTerm.toLowerCase());

            const matchesResource = resourceFilter === 'all' || permission.resource === resourceFilter;

            const matchesAssignment = !showOnlyAssigned || roles.some((role) => hasRolePermission(role, permission));

            return matchesSearch && matchesResource && matchesAssignment;
        });
    }, [permissions, searchTerm, resourceFilter, showOnlyAssigned, roles]);

    // Filter roles
    const filteredRoles = useMemo(() => {
        let filtered = roles;

        if (roleFilter !== 'all') {
            if (roleFilter === 'active') {
                filtered = filtered.filter((role) => role.is_active);
            } else if (roleFilter === 'inactive') {
                filtered = filtered.filter((role) => !role.is_active);
            }
        }

        return filtered.sort((a, b) => a.hierarchy_level - b.hierarchy_level);
    }, [roles, roleFilter]);

    // Get statistics for the matrix
    const stats = useMemo(() => {
        const totalAssignments = filteredRoles.reduce((acc, role) => acc + (role.permissions?.length || 0), 0);
        const possibleAssignments = filteredRoles.length * filteredPermissions.length;
        const coveragePercentage = possibleAssignments > 0 ? (totalAssignments / possibleAssignments) * 100 : 0;

        return {
            totalAssignments,
            possibleAssignments,
            coveragePercentage: Math.round(coveragePercentage * 100) / 100,
        };
    }, [filteredRoles, filteredPermissions]);

    const togglePermission = async (role: Role, permission: Permission) => {
        if (!editMode || !canEditMatrix) return;

        const cellKey = `${role.id}-${permission.id}`;
        setLoadingCells((prev) => new Set(prev).add(cellKey));
        setError(null);

        const currentlyHasPermission = hasRolePermission(role, permission);
        const newGrantedState = !currentlyHasPermission;

        try {
            const response = await fetch('/admin/roles/matrix/update', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    role_id: role.id,
                    permission_id: permission.id,
                    granted: newGrantedState,
                }),
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to update permission');
            }

            // Update the role's permissions locally for immediate UI feedback
            if (newGrantedState) {
                // Add permission
                role.permissions = [...(role.permissions || []), permission];
            } else {
                // Remove permission
                role.permissions = (role.permissions || []).filter((p) => p.id !== permission.id);
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to update permission');
        } finally {
            setLoadingCells((prev) => {
                const newSet = new Set(prev);
                newSet.delete(cellKey);
                return newSet;
            });
        }
    };

    const handleEditModeToggle = () => {
        if (!canEditMatrix) {
            setError('You do not have permission to edit the permission matrix.');
            return;
        }
        setEditMode(!editMode);
        setError(null);
    };

    const exportMatrix = () => {
        // Create CSV content
        const headers = ['Permission', 'Resource', ...filteredRoles.map((role) => role.display_name)];
        const rows = filteredPermissions.map((permission) => [
            permission.display_name,
            permission.resource,
            ...filteredRoles.map((role) => (hasRolePermission(role, permission) ? 'Yes' : 'No')),
        ]);

        const csvContent = [headers, ...rows].map((row) => row.join(',')).join('\n');

        // Download CSV
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `permission-matrix-${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    };

    const clearFilters = () => {
        setSearchTerm('');
        setResourceFilter('all');
        setRoleFilter('all');
        setShowOnlyAssigned(false);
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="flex h-[95vh] max-w-[95vw] flex-col overflow-y-auto xl:max-w-[90vw]">
                <DialogHeader>
                    <DialogTitle>Permission Matrix</DialogTitle>
                    <DialogDescription>
                        {editMode
                            ? 'Click on cells to toggle permissions for roles. Changes are saved immediately.'
                            : 'View role permissions across your system.'}
                        {canEditMatrix ? ' Switch to edit mode to make changes.' : ' You have read-only access.'}
                    </DialogDescription>
                </DialogHeader>

                {error && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-1 flex-col gap-6">
                    {/* Stats */}
                    <div className="grid grid-cols-3 gap-4">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm">Total Assignments</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.totalAssignments}</div>
                                <p className="text-muted-foreground text-xs">out of {stats.possibleAssignments} possible</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm">Coverage</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.coveragePercentage}%</div>
                                <p className="text-muted-foreground text-xs">permissions assigned</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm">Filtered View</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {filteredRoles.length}×{filteredPermissions.length}
                                </div>
                                <p className="text-muted-foreground text-xs">roles × permissions</p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Filters */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm">Filters & Actions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-wrap items-center gap-4">
                                <div className="min-w-[200px] flex-1">
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
                                    <SelectTrigger className="w-[150px]">
                                        <SelectValue placeholder="Resource" />
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

                                <Select value={roleFilter} onValueChange={setRoleFilter}>
                                    <SelectTrigger className="w-[150px]">
                                        <SelectValue placeholder="Roles" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Roles</SelectItem>
                                        <SelectItem value="active">Active Roles</SelectItem>
                                        <SelectItem value="inactive">Inactive Roles</SelectItem>
                                    </SelectContent>
                                </Select>

                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="showOnlyAssigned"
                                        checked={showOnlyAssigned}
                                        onCheckedChange={(checked) => setShowOnlyAssigned(!!checked)}
                                    />
                                    <label htmlFor="showOnlyAssigned" className="text-sm">
                                        Only assigned
                                    </label>
                                </div>

                                <Button variant="outline" size="sm" onClick={clearFilters}>
                                    Clear
                                </Button>

                                {canEditMatrix && (
                                    <Button variant={editMode ? 'default' : 'outline'} size="sm" onClick={handleEditModeToggle} className="gap-2">
                                        {editMode ? <Edit className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        {editMode ? 'Edit Mode' : 'View Mode'}
                                    </Button>
                                )}

                                <Button variant="outline" size="sm" onClick={exportMatrix} className="gap-2">
                                    <Download className="h-4 w-4" />
                                    Export CSV
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Matrix Table */}
                    <Card className="flex-1 overflow-hidden">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm">
                                Matrix View ({filteredPermissions.length} permissions × {filteredRoles.length} roles)
                                {editMode && canEditMatrix && <span className="ml-2 text-blue-600">• Edit Mode Active</span>}
                                {!canEditMatrix && <span className="ml-2 text-amber-600">• Read Only</span>}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="h-full overflow-auto">
                                <table className="w-full border-collapse">
                                    <thead className="bg-background sticky top-0 border-b">
                                        <tr>
                                            <th className="bg-muted min-w-[250px] border-r p-3 text-left font-medium">Permission</th>
                                            <th className="bg-muted min-w-[120px] border-r p-3 text-left font-medium">Resource</th>
                                            {filteredRoles.map((role) => (
                                                <th key={role.id} className="bg-muted min-w-[100px] border-r p-2 text-center font-medium">
                                                    <div className="flex flex-col items-center gap-1">
                                                        <span className="max-w-[80px] truncate text-xs font-medium">{role.display_name}</span>
                                                        <Badge variant="outline" className="h-4 px-1 text-xs">
                                                            L{role.hierarchy_level}
                                                        </Badge>
                                                        {!role.is_active && (
                                                            <Badge variant="secondary" className="h-4 px-1 text-xs">
                                                                Inactive
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {Object.entries(permissionsByResource).map(([resource, resourcePermissions]) => {
                                            const filteredResourcePermissions = resourcePermissions.filter((perm) =>
                                                filteredPermissions.includes(perm),
                                            );

                                            if (filteredResourcePermissions.length === 0) return null;

                                            return (
                                                <React.Fragment key={resource}>
                                                    {/* Resource Header */}
                                                    <tr className="bg-muted/50">
                                                        <td colSpan={2 + filteredRoles.length} className="border-t border-b p-2 text-sm font-medium">
                                                            <div className="flex items-center gap-2">
                                                                <span className="capitalize">{resource}</span>
                                                                <Badge variant="outline" className="text-xs">
                                                                    {filteredResourcePermissions.length} permissions
                                                                </Badge>
                                                            </div>
                                                        </td>
                                                    </tr>

                                                    {/* Permission Rows */}
                                                    {filteredResourcePermissions.map((permission, permIndex) => (
                                                        <tr
                                                            key={permission.id}
                                                            className={`hover:bg-muted/50 ${permIndex % 2 === 0 ? 'bg-background' : 'bg-muted/20'}`}
                                                        >
                                                            <td className="border-r p-3">
                                                                <div>
                                                                    <div className="text-sm font-medium">{permission.display_name}</div>
                                                                    <div className="text-muted-foreground text-xs">{permission.name}</div>
                                                                </div>
                                                            </td>
                                                            <td className="border-r p-3">
                                                                <Badge variant="outline" className="text-xs">
                                                                    {permission.resource}
                                                                </Badge>
                                                            </td>
                                                            {filteredRoles.map((role) => {
                                                                const cellKey = `${role.id}-${permission.id}`;
                                                                const isLoading = loadingCells.has(cellKey);
                                                                const hasPermissionValue = hasRolePermission(role, permission);

                                                                return (
                                                                    <td key={`${permission.id}-${role.id}`} className="border-r p-2 text-center">
                                                                        <button
                                                                            className={`flex h-8 w-8 items-center justify-center rounded transition-colors ${
                                                                                editMode && canEditMatrix
                                                                                    ? 'hover:bg-gray-100 focus:ring-2 focus:ring-blue-500 focus:outline-none'
                                                                                    : 'cursor-default'
                                                                            }`}
                                                                            onClick={() => togglePermission(role, permission)}
                                                                            disabled={!editMode || !canEditMatrix || isLoading}
                                                                            title={
                                                                                editMode && canEditMatrix
                                                                                    ? 'Click to toggle permission'
                                                                                    : canEditMatrix
                                                                                      ? 'Enable edit mode to modify'
                                                                                      : 'Read-only access'
                                                                            }
                                                                        >
                                                                            {isLoading ? (
                                                                                <Loader2 className="h-4 w-4 animate-spin text-blue-600" />
                                                                            ) : hasPermissionValue ? (
                                                                                <Check className="h-4 w-4 text-green-600" />
                                                                            ) : (
                                                                                <Minus className="text-muted-foreground h-4 w-4" />
                                                                            )}
                                                                        </button>
                                                                    </td>
                                                                );
                                                            })}
                                                        </tr>
                                                    ))}
                                                </React.Fragment>
                                            );
                                        })}
                                    </tbody>
                                </table>

                                {filteredPermissions.length === 0 && (
                                    <div className="flex flex-col items-center justify-center py-12">
                                        <Filter className="text-muted-foreground mb-4 h-12 w-12" />
                                        <h3 className="mb-2 text-lg font-semibold">No permissions found</h3>
                                        <p className="text-muted-foreground mb-4 text-center">No permissions match your current filters.</p>
                                        <Button variant="outline" onClick={clearFilters}>
                                            Clear Filters
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex justify-end gap-4 border-t pt-4">
                        {editMode && canEditMatrix && (
                            <div className="text-muted-foreground mr-auto text-sm">
                                💡 Click on any cell to toggle permissions. Changes are saved immediately.
                            </div>
                        )}
                        {!canEditMatrix && (
                            <div className="text-muted-foreground mr-auto text-sm">ℹ️ You have read-only access to the permission matrix.</div>
                        )}
                        <Button variant="outline" onClick={onClose}>
                            Close
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
