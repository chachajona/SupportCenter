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
import { CheckSquare, HelpCircle, Keyboard, RotateCcw, Save, Search, Shield, Square, Users } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
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
    // New state for enhanced UX
    const [focusedCell, setFocusedCell] = useState<{ row: number; col: number } | null>(null);
    const [showKeyboardHelp, setShowKeyboardHelp] = useState(false);
    const tableRef = useRef<HTMLTableElement>(null);

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

    // Get active roles for display
    const activeRoles = roles.filter((role) => role.is_active);

    // Helper function to check if role has permission
    const hasPermission = useCallback(
        (roleId: number, permissionId: number): boolean => {
            const key = `${roleId}-${permissionId}`;
            if (Object.hasOwn(changes, key)) {
                return changes[key];
            }
            return matrix.some((entry) => entry.role_id === roleId && entry.permission_id === permissionId && entry.granted);
        },
        [changes, matrix],
    );

    // Handle permission toggle
    const togglePermission = useCallback(
        (roleId: number, permissionId: number) => {
            const key = `${roleId}-${permissionId}`;
            const currentValue = hasPermission(roleId, permissionId);
            setChanges((prev) => ({
                ...prev,
                [key]: !currentValue,
            }));
        },
        [hasPermission],
    );

    // Batch operations
    const toggleAllPermissionsForRole = (roleId: number) => {
        const role = activeRoles.find((r) => r.id === roleId);
        if (role?.is_system && role.name === 'system_administrator') {
            toast.error('Cannot modify system administrator permissions');
            return;
        }

        const permissionCount = filteredPermissions.filter((p) => hasPermission(roleId, p.id)).length;
        const shouldGrantAll = permissionCount < filteredPermissions.length / 2;

        filteredPermissions.forEach((permission) => {
            const key = `${roleId}-${permission.id}`;
            setChanges((prev) => ({
                ...prev,
                [key]: shouldGrantAll,
            }));
        });

        toast.success(`${shouldGrantAll ? 'Granted' : 'Revoked'} all permissions for ${role?.display_name}`);
    };

    const togglePermissionForAllRoles = (permissionId: number) => {
        const permission = filteredPermissions.find((p) => p.id === permissionId);
        const roleCount = activeRoles.filter((r) => hasPermission(r.id, permissionId)).length;
        const shouldGrantAll = roleCount < activeRoles.length / 2;

        activeRoles.forEach((role) => {
            if (role.is_system && role.name === 'system_administrator') return;

            const key = `${role.id}-${permissionId}`;
            setChanges((prev) => ({
                ...prev,
                [key]: shouldGrantAll,
            }));
        });

        toast.success(`${shouldGrantAll ? 'Granted' : 'Revoked'} "${permission?.display_name}" for all roles`);
    };

    const clearAllChanges = () => {
        setChanges({});
        toast.success('All pending changes cleared');
    };

    // Keyboard navigation
    const handleKeyDown = useCallback(
        (e: KeyboardEvent) => {
            if (!focusedCell) return;

            const { row, col } = focusedCell;
            const maxRow = filteredPermissions.length - 1;
            const maxCol = activeRoles.length - 1;

            switch (e.key) {
                case 'ArrowUp':
                    e.preventDefault();
                    if (row > 0) {
                        setFocusedCell({ row: row - 1, col });
                    }
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    if (row < maxRow) {
                        setFocusedCell({ row: row + 1, col });
                    }
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    if (col > 0) {
                        setFocusedCell({ row, col: col - 1 });
                    }
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    if (col < maxCol) {
                        setFocusedCell({ row, col: col + 1 });
                    }
                    break;
                case 'Enter':
                case ' ': {
                    e.preventDefault();
                    const permission = filteredPermissions[row];
                    const role = activeRoles[col];
                    if (permission && role) {
                        togglePermission(role.id, permission.id);
                    }
                    break;
                }
                case 'Escape':
                    setFocusedCell(null);
                    break;
                case '?':
                    if (e.shiftKey) {
                        setShowKeyboardHelp(!showKeyboardHelp);
                    }
                    break;
            }
        },
        [focusedCell, filteredPermissions, activeRoles, togglePermission, showKeyboardHelp, setShowKeyboardHelp],
    );

    useEffect(() => {
        if (focusedCell) {
            document.addEventListener('keydown', handleKeyDown);
            return () => document.removeEventListener('keydown', handleKeyDown);
        }
    }, [handleKeyDown, focusedCell]);

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
                                <p className="text-muted-foreground">
                                    Manage role permissions across your system. Use keyboard navigation or batch operations for efficiency.
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setShowKeyboardHelp(!showKeyboardHelp)}
                                    title="Show keyboard shortcuts"
                                >
                                    <Keyboard className="mr-2 h-4 w-4" />
                                    Shortcuts
                                </Button>
                                {hasChanges && (
                                    <>
                                        <Button variant="outline" onClick={clearAllChanges} size="sm">
                                            <RotateCcw className="mr-2 h-4 w-4" />
                                            Clear ({Object.keys(changes).length})
                                        </Button>
                                        <Button onClick={saveChanges} disabled={saving}>
                                            <Save className="mr-2 h-4 w-4" />
                                            {saving ? 'Updating...' : `Update (${Object.keys(changes).length})`}
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>

                        {/* Keyboard Help */}
                        {showKeyboardHelp && (
                            <Card className="border-blue-200 bg-blue-50">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-blue-900">
                                        <HelpCircle className="h-5 w-5" />
                                        Keyboard Shortcuts
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-2 text-sm text-blue-800 md:grid-cols-2">
                                    <div>
                                        <kbd className="rounded bg-blue-100 px-1.5 py-0.5">↑ ↓ ← →</kbd> Navigate cells
                                    </div>
                                    <div>
                                        <kbd className="rounded bg-blue-100 px-1.5 py-0.5">Enter / Space</kbd> Toggle permission
                                    </div>
                                    <div>
                                        <kbd className="rounded bg-blue-100 px-1.5 py-0.5">Escape</kbd> Exit cell focus
                                    </div>
                                    <div>
                                        <kbd className="rounded bg-blue-100 px-1.5 py-0.5">Shift + ?</kbd> Toggle this help
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        <div className="flex items-center gap-4">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground absolute top-3 left-3 h-4 w-4" />
                                <Input
                                    placeholder="Search permissions..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="pl-10"
                                    aria-label="Search permissions"
                                />
                            </div>
                            <select
                                value={selectedResource}
                                onChange={(e) => setSelectedResource(e.target.value)}
                                className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                                aria-label="Filter by resource"
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
                                    <span className="text-muted-foreground text-sm font-normal">
                                        ({filteredPermissions.length} permissions × {activeRoles.length} roles)
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <Table ref={tableRef} role="grid" aria-label="Permission matrix">
                                        <TableHeader>
                                            <TableRow role="row">
                                                <TableHead className="w-[300px]" role="columnheader">
                                                    Permission
                                                </TableHead>
                                                {activeRoles.map((role) => (
                                                    <TableHead key={role.id} className="min-w-[120px] text-center" role="columnheader">
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
                                                            {/* Batch operation button for role */}
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-6 text-xs"
                                                                onClick={() => toggleAllPermissionsForRole(role.id)}
                                                                disabled={role.is_system && role.name === 'system_administrator'}
                                                                title={`Toggle all permissions for ${role.display_name}`}
                                                                aria-label={`Toggle all permissions for ${role.display_name}`}
                                                            >
                                                                <CheckSquare className="h-3 w-3" />
                                                            </Button>
                                                        </div>
                                                    </TableHead>
                                                ))}
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {filteredPermissions.map((permission, rowIndex) => (
                                                <TableRow key={permission.id} role="row">
                                                    <TableCell role="gridcell">
                                                        <div className="flex items-center justify-between">
                                                            <div className="space-y-1">
                                                                <div className="font-medium">{permission.display_name}</div>
                                                                <div className="text-muted-foreground text-sm">{permission.name}</div>
                                                                <Badge variant="outline" className="text-xs">
                                                                    {permission.resource}
                                                                </Badge>
                                                            </div>
                                                            {/* Batch operation button for permission */}
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="ml-2 h-6 text-xs"
                                                                onClick={() => togglePermissionForAllRoles(permission.id)}
                                                                title={`Grant/revoke "${permission.display_name}" for all roles`}
                                                                aria-label={`Grant or revoke ${permission.display_name} for all roles`}
                                                            >
                                                                <Square className="h-3 w-3" />
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                    {activeRoles.map((role, colIndex) => {
                                                        const isDisabled = role.is_system && role.name === 'system_administrator';
                                                        const isFocused = focusedCell?.row === rowIndex && focusedCell?.col === colIndex;
                                                        const hasPermissionValue = hasPermission(role.id, permission.id);

                                                        return (
                                                            <TableCell
                                                                key={role.id}
                                                                role="gridcell"
                                                                tabIndex={isFocused ? 0 : -1}
                                                                onFocus={() => setFocusedCell({ row: rowIndex, col: colIndex })}
                                                                onBlur={() => setFocusedCell(null)}
                                                                aria-label={`${hasPermissionValue ? 'Granted' : 'Not granted'}: ${permission.display_name} for ${role.display_name}`}
                                                                className={`text-center transition-colors ${
                                                                    isFocused ? 'bg-blue-50 ring-2 ring-blue-500' : ''
                                                                }`}
                                                            >
                                                                <Switch
                                                                    checked={hasPermissionValue}
                                                                    onCheckedChange={() => togglePermission(role.id, permission.id)}
                                                                    disabled={isDisabled}
                                                                    aria-label={`${hasPermissionValue ? 'Revoke' : 'Grant'} ${permission.display_name} for ${role.display_name}`}
                                                                />
                                                            </TableCell>
                                                        );
                                                    })}
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
                                            <p className="text-2xl font-bold">{activeRoles.length}</p>
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
                                            <p className="text-sm font-medium">Pending Changes</p>
                                            <p className="text-2xl font-bold">{Object.keys(changes).length}</p>
                                        </div>
                                        <Save className="text-muted-foreground h-8 w-8" />
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
