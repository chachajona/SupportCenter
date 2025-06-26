import { PermissionGate } from '@/components/rbac/permission-gate';
import { RoleAssignmentDialog } from '@/components/rbac/role-assignment-dialog';
import { TemporalAccessForm } from '@/components/rbac/temporal-access-form';
import { TemporalDelegationDialog } from '@/components/rbac/temporal-delegation-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Role, User, UserRole } from '@/types/rbac';
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Calendar, CalendarDays, Clock, History, Plus, Shield, Users, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface UserRoleManagementProps {
    user: User;
    userRoles: UserRole[];
    availableRoles: Role[];
    roleHistory: Array<{
        id: number;
        role: Role;
        action: 'granted' | 'revoked';
        granted_by?: User;
        granted_at: string;
        expires_at?: string;
        reason?: string;
    }>;
    permanentRoles: Array<{
        id: number;
        name: string;
        display_name: string;
        description: string;
        pivot: {
            granted_at: string;
            granted_by?: number;
        };
    }>;
    temporalRoles: Array<{
        id: number;
        name: string;
        display_name: string;
        description: string;
        pivot: {
            granted_at: string;
            expires_at: string;
            granted_by?: number;
            delegation_reason?: string;
        };
    }>;
    canManageRoles: boolean;
}

export default function UserRoleManagement({
    user,
    userRoles,
    availableRoles,
    roleHistory,
    permanentRoles,
    temporalRoles,
    canManageRoles,
}: UserRoleManagementProps) {
    const [showAssignDialog, setShowAssignDialog] = useState(false);
    const [showTemporalForm, setShowTemporalForm] = useState(false);
    const [showHistory, setShowHistory] = useState(false);
    const [delegationDialogOpen, setDelegationDialogOpen] = useState(false);

    const handleRevokeRole = (roleId: number, roleName: string, isTemporal: boolean = false) => {
        if (!confirm(`Are you sure you want to revoke the "${roleName}" role from ${user.name}?`)) {
            return;
        }

        const endpoint = isTemporal ? `/admin/users/${user.id}/temporal-access/${roleId}` : `/admin/users/${user.id}/roles/${roleId}`;

        router.delete(endpoint, {
            onSuccess: () => {
                toast.success(`Role "${roleName}" revoked from ${user.name}`);
            },
            onError: () => {
                toast.error('Failed to revoke role');
            },
        });
    };

    const getTimeBadgeVariant = (expiresAt?: string) => {
        if (!expiresAt) return 'default';

        const now = new Date();
        const expires = new Date(expiresAt);
        const hoursUntilExpiry = (expires.getTime() - now.getTime()) / (1000 * 60 * 60);

        if (hoursUntilExpiry < 0) return 'destructive'; // Expired
        if (hoursUntilExpiry < 24) return 'destructive'; // Expires within 24 hours
        if (hoursUntilExpiry < 72) return 'secondary'; // Expires within 3 days
        return 'outline';
    };

    const isExpired = (expiresAt?: string) => {
        if (!expiresAt) return false;
        return new Date(expiresAt) < new Date();
    };

    const formatTimeRemaining = (expiresAt: string) => {
        const now = new Date();
        const expires = new Date(expiresAt);
        const diff = expires.getTime() - now.getTime();

        if (diff < 0) return 'Expired';

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

        if (days > 0) return `${days}d ${hours}h`;
        if (hours > 0) return `${hours}h ${minutes}m`;
        return `${minutes}m`;
    };

    // Separate active and expired roles
    const activeRoles = userRoles.filter((ur) => ur.is_active && !isExpired(ur.expires_at));
    const expiredRoles = userRoles.filter((ur) => !ur.is_active || isExpired(ur.expires_at));

    const getTimeUntilExpiry = (expiresAt: string) => {
        const now = new Date();
        const expiry = new Date(expiresAt);
        const diffMs = expiry.getTime() - now.getTime();

        if (diffMs <= 0) return 'Expired';

        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

        if (diffHours > 0) {
            return `${diffHours}h ${diffMinutes}m`;
        }
        return `${diffMinutes}m`;
    };

    return (
        <>
            <Head title={`Manage Roles - ${user.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Manage Roles for {user.name}</h1>
                        <p className="text-muted-foreground">Assign and manage user roles and permissions</p>
                    </div>

                    <div className="flex items-center gap-4">
                        <Button variant="outline" onClick={() => setShowHistory(!showHistory)} className="gap-2">
                            <History className="h-4 w-4" />
                            {showHistory ? 'Hide' : 'Show'} History
                        </Button>

                        <PermissionGate permission="roles.assign_temporal">
                            <Button variant="outline" onClick={() => setShowTemporalForm(true)} className="gap-2">
                                <Clock className="h-4 w-4" />
                                Temporary Access
                            </Button>
                        </PermissionGate>

                        <PermissionGate permission="roles.assign">
                            <Button onClick={() => setShowAssignDialog(true)} className="gap-2">
                                <Plus className="h-4 w-4" />
                                Assign Role
                            </Button>
                        </PermissionGate>

                        {canManageRoles && (
                            <Button onClick={() => setDelegationDialogOpen(true)} className="flex items-center gap-2">
                                <Clock className="h-4 w-4" />
                                Grant Temporal Access
                            </Button>
                        )}
                    </div>
                </div>

                {/* User Summary */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="h-5 w-5" />
                            User Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <h4 className="mb-2 font-medium">Basic Details</h4>
                                <div className="space-y-1 text-sm">
                                    <p>
                                        <strong>Name:</strong> {user.name}
                                    </p>
                                    <p>
                                        <strong>Email:</strong> {user.email}
                                    </p>
                                    {user.department && (
                                        <p>
                                            <strong>Department:</strong> {user.department.name}
                                        </p>
                                    )}
                                    <div className="flex items-center gap-2">
                                        <strong>Status:</strong>
                                        <Badge variant={user.is_active ? 'default' : 'secondary'}>{user.is_active ? 'Active' : 'Inactive'}</Badge>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <h4 className="mb-2 font-medium">Role Summary</h4>
                                <div className="space-y-1 text-sm">
                                    <p>
                                        <strong>Active Roles:</strong> {activeRoles.length}
                                    </p>
                                    <p>
                                        <strong>Expired Roles:</strong> {expiredRoles.length}
                                    </p>
                                    <p>
                                        <strong>Temporary Roles:</strong> {activeRoles.filter((ur) => ur.expires_at).length}
                                    </p>
                                </div>
                            </div>
                            <div>
                                <h4 className="mb-2 font-medium">Permission Count</h4>
                                <div className="space-y-1 text-sm">
                                    <p>
                                        <strong>Total Permissions:</strong>{' '}
                                        {[...new Set(activeRoles.flatMap((ur) => ur.role.permissions?.map((p) => p.id) || []))].length}
                                    </p>
                                    <p>
                                        <strong>Unique Resources:</strong>{' '}
                                        {[...new Set(activeRoles.flatMap((ur) => ur.role.permissions?.map((p) => p.resource) || []))].length}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Active Roles */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Shield className="h-5 w-5" />
                            Active Roles ({activeRoles.length})
                        </CardTitle>
                        <CardDescription>Currently active role assignments for this user</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {activeRoles.length === 0 ? (
                            <div className="py-8 text-center">
                                <Shield className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                <h3 className="mb-2 text-lg font-semibold">No active roles</h3>
                                <p className="text-muted-foreground mb-4">This user has no active role assignments.</p>
                                <PermissionGate permission="roles.assign">
                                    <Button onClick={() => setShowAssignDialog(true)} className="gap-2">
                                        <Plus className="h-4 w-4" />
                                        Assign First Role
                                    </Button>
                                </PermissionGate>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {activeRoles.map((userRole) => (
                                    <div key={userRole.id} className="flex items-center justify-between rounded-lg border p-4">
                                        <div className="flex-1 space-y-2">
                                            <div className="flex items-center gap-2">
                                                <h3 className="font-medium">{userRole.role.display_name}</h3>
                                                <Badge variant="outline" className="text-xs">
                                                    Level {userRole.role.hierarchy_level}
                                                </Badge>
                                                {userRole.expires_at && (
                                                    <Badge variant={getTimeBadgeVariant(userRole.expires_at)} className="gap-1">
                                                        <Clock className="h-3 w-3" />
                                                        {formatTimeRemaining(userRole.expires_at)}
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-muted-foreground text-sm">{userRole.role.description}</p>

                                            <div className="text-muted-foreground flex items-center gap-4 text-sm">
                                                <span>Granted by: {userRole.granted_by_user?.name || 'System'}</span>
                                                <span>•</span>
                                                <span>On: {new Date(userRole.granted_at).toLocaleDateString()}</span>
                                                {userRole.expires_at && (
                                                    <>
                                                        <span>•</span>
                                                        <span className={isExpired(userRole.expires_at) ? 'text-red-600' : ''}>
                                                            Expires: {new Date(userRole.expires_at).toLocaleString()}
                                                        </span>
                                                    </>
                                                )}
                                            </div>

                                            {userRole.delegation_reason && (
                                                <div className="bg-muted mt-2 rounded p-2 text-sm">
                                                    <p className="font-medium">Reason:</p>
                                                    <p className="text-muted-foreground">{userRole.delegation_reason}</p>
                                                </div>
                                            )}

                                            <div className="flex items-center gap-2 text-sm">
                                                <span className="text-muted-foreground">Permissions:</span>
                                                <Badge variant="outline">{userRole.role.permissions?.length || 0}</Badge>
                                                {userRole.role.permissions && userRole.role.permissions.length > 0 && (
                                                    <div className="ml-2 flex flex-wrap gap-1">
                                                        {[...new Set(userRole.role.permissions.map((p) => p.resource))]
                                                            .slice(0, 3)
                                                            .map((resource) => (
                                                                <Badge key={resource} variant="secondary" className="text-xs">
                                                                    {resource}
                                                                </Badge>
                                                            ))}
                                                        {[...new Set(userRole.role.permissions.map((p) => p.resource))].length > 3 && (
                                                            <Badge variant="secondary" className="text-xs">
                                                                +{[...new Set(userRole.role.permissions.map((p) => p.resource))].length - 3} more
                                                            </Badge>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        <div className="ml-4 flex items-center gap-2">
                                            {userRole.expires_at && isExpired(userRole.expires_at) && (
                                                <Badge variant="destructive" className="gap-1">
                                                    <AlertTriangle className="h-3 w-3" />
                                                    Expired
                                                </Badge>
                                            )}

                                            <PermissionGate permission="roles.revoke">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleRevokeRole(userRole.id, userRole.role.display_name)}
                                                    className="text-destructive hover:text-destructive gap-2"
                                                >
                                                    <X className="h-4 w-4" />
                                                    Revoke
                                                </Button>
                                            </PermissionGate>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Expired/Inactive Roles */}
                {expiredRoles.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <AlertTriangle className="text-muted-foreground h-5 w-5" />
                                Expired/Inactive Roles ({expiredRoles.length})
                            </CardTitle>
                            <CardDescription>Previous role assignments that are no longer active</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {expiredRoles.map((userRole) => (
                                    <div key={userRole.id} className="flex items-center justify-between rounded-lg border p-3 opacity-60">
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <h4 className="text-sm font-medium">{userRole.role.display_name}</h4>
                                                <Badge variant="secondary" className="text-xs">
                                                    Expired
                                                </Badge>
                                            </div>
                                            <p className="text-muted-foreground text-xs">
                                                {userRole.expires_at ? `Expired: ${new Date(userRole.expires_at).toLocaleString()}` : 'Revoked'}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Role History */}
                {showHistory && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <History className="h-5 w-5" />
                                Role Assignment History
                            </CardTitle>
                            <CardDescription>Complete history of role changes for this user</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {roleHistory.length === 0 ? (
                                <p className="text-muted-foreground py-4 text-center">No role history available</p>
                            ) : (
                                <div className="space-y-3">
                                    {roleHistory.map((entry) => (
                                        <div key={entry.id} className="flex items-center gap-4 rounded-lg border p-3">
                                            <Badge variant={entry.action === 'granted' ? 'default' : 'destructive'}>{entry.action}</Badge>
                                            <div className="flex-1">
                                                <p className="text-sm font-medium">{entry.role.display_name}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {entry.action === 'granted' ? 'Granted' : 'Revoked'} by {entry.granted_by?.name || 'System'} on{' '}
                                                    {new Date(entry.granted_at).toLocaleString()}
                                                </p>
                                                {entry.reason && <p className="text-muted-foreground mt-1 text-xs">Reason: {entry.reason}</p>}
                                            </div>
                                            {entry.expires_at && (
                                                <Badge variant="outline" className="gap-1 text-xs">
                                                    <Calendar className="h-3 w-3" />
                                                    Until {new Date(entry.expires_at).toLocaleDateString()}
                                                </Badge>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Permanent Roles */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Shield className="h-5 w-5 text-green-600" />
                            Permanent Roles
                        </CardTitle>
                        <CardDescription>Long-term role assignments that don't expire automatically</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {permanentRoles.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Description</TableHead>
                                        <TableHead>Granted</TableHead>
                                        {canManageRoles && <TableHead>Actions</TableHead>}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {permanentRoles.map((role) => (
                                        <TableRow key={role.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">{role.display_name}</span>
                                                    <Badge variant="secondary">Permanent</Badge>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-gray-600">{role.description}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1 text-sm text-gray-500">
                                                    <CalendarDays className="h-3 w-3" />
                                                    {new Date(role.pivot.granted_at).toLocaleDateString()}
                                                </div>
                                            </TableCell>
                                            {canManageRoles && (
                                                <TableCell>
                                                    <Button variant="outline" size="sm" onClick={() => handleRevokeRole(role.id, role.display_name)}>
                                                        Revoke
                                                    </Button>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="py-8 text-center text-gray-500">
                                <Shield className="mx-auto mb-2 h-8 w-8 opacity-50" />
                                <p>No permanent roles assigned</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Temporal Roles */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Clock className="h-5 w-5 text-blue-600" />
                            Temporal Access
                        </CardTitle>
                        <CardDescription>Temporary role assignments with automatic expiration</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {temporalRoles.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Reason</TableHead>
                                        <TableHead>Expires</TableHead>
                                        <TableHead>Status</TableHead>
                                        {canManageRoles && <TableHead>Actions</TableHead>}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {temporalRoles.map((role) => {
                                        const expired = isExpired(role.pivot.expires_at);
                                        const timeLeft = getTimeUntilExpiry(role.pivot.expires_at);

                                        return (
                                            <TableRow key={role.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-medium">{role.display_name}</span>
                                                        <Badge variant={expired ? 'destructive' : 'default'}>Temporal</Badge>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-gray-600">
                                                    {role.pivot.delegation_reason || 'No reason provided'}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1 text-sm">
                                                        <CalendarDays className="h-3 w-3" />
                                                        {new Date(role.pivot.expires_at).toLocaleString()}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {expired ? (
                                                        <Badge variant="destructive" className="flex items-center gap-1">
                                                            <AlertTriangle className="h-3 w-3" />
                                                            Expired
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="default" className="flex items-center gap-1">
                                                            <Clock className="h-3 w-3" />
                                                            {timeLeft} left
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                {canManageRoles && (
                                                    <TableCell>
                                                        {!expired && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => handleRevokeRole(role.id, role.display_name, true)}
                                                            >
                                                                Revoke
                                                            </Button>
                                                        )}
                                                    </TableCell>
                                                )}
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="py-8 text-center text-gray-500">
                                <Clock className="mx-auto mb-2 h-8 w-8 opacity-50" />
                                <p>No temporal access granted</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Dialogs */}
            <RoleAssignmentDialog
                user={user}
                availableRoles={availableRoles}
                assignedRoles={activeRoles.map((ur) => ur.role)}
                open={showAssignDialog}
                onClose={() => setShowAssignDialog(false)}
            />

            <TemporalAccessForm user={user} availableRoles={availableRoles} open={showTemporalForm} onClose={() => setShowTemporalForm(false)} />

            <TemporalDelegationDialog open={delegationDialogOpen} onOpenChange={setDelegationDialogOpen} user={user} roles={availableRoles} />
        </>
    );
}
