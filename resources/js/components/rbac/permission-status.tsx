import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useRBAC } from '@/contexts/RBACContext';
import { cn } from '@/lib/utils';
import { AlertTriangle, CheckCircle, Clock, Shield, XCircle } from 'lucide-react';

export interface PermissionStatusProps {
    /**
     * Permission to check
     */
    permission?: string;

    /**
     * Role to check
     */
    role?: string;

    /**
     * Show detailed status
     */
    detailed?: boolean;

    /**
     * Show as inline badge
     */
    inline?: boolean;

    /**
     * Custom className
     */
    className?: string;
}

/**
 * Component that displays the current permission status for a user
 * Provides visual feedback about access rights with accessibility features
 */
export function PermissionStatus({ permission, role, detailed = false, inline = false, className }: PermissionStatusProps) {
    const { hasPermission, hasRole, loading, user } = useRBAC();

    if (!user) {
        return null;
    }

    const hasAccess = permission ? hasPermission(permission) : role ? hasRole(role) : false;
    const label = permission || role || 'Unknown';

    const getStatusIcon = () => {
        if (loading) {
            return <Clock className="text-muted-foreground h-4 w-4 animate-spin" />;
        }

        return hasAccess ? <CheckCircle className="h-4 w-4 text-green-600" /> : <XCircle className="h-4 w-4 text-red-600" />;
    };

    const getStatusBadge = () => {
        if (loading) {
            return (
                <Badge variant="outline" className="gap-1">
                    <Clock className="h-3 w-3 animate-spin" />
                    Loading
                </Badge>
            );
        }

        return hasAccess ? (
            <Badge variant="default" className="gap-1 bg-green-100 text-green-800">
                <CheckCircle className="h-3 w-3" />
                Granted
            </Badge>
        ) : (
            <Badge variant="destructive" className="gap-1">
                <XCircle className="h-3 w-3" />
                Denied
            </Badge>
        );
    };

    const getTooltipContent = () => {
        if (loading) {
            return 'Checking permissions...';
        }

        if (permission) {
            return hasAccess ? `Permission granted: ${permission}` : `Permission denied: ${permission}`;
        }

        if (role) {
            return hasAccess ? `Role assigned: ${role}` : `Role not assigned: ${role}`;
        }

        return 'Unknown permission status';
    };

    if (inline) {
        return (
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span className={cn('inline-flex items-center gap-1', className)}>
                            {getStatusIcon()}
                            {detailed && (
                                <span className={cn('text-sm font-medium', hasAccess ? 'text-green-700' : 'text-red-700')}>
                                    {hasAccess ? 'Granted' : 'Denied'}
                                </span>
                            )}
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>{getTooltipContent()}</TooltipContent>
                </Tooltip>
            </TooltipProvider>
        );
    }

    if (detailed) {
        return (
            <div className={cn('flex items-center justify-between rounded-lg border p-3', className)}>
                <div className="flex items-center gap-3">
                    <Shield className="text-muted-foreground h-5 w-5" />
                    <div>
                        <p className="font-medium">{label}</p>
                        <p className="text-muted-foreground text-sm">{permission ? 'Permission' : 'Role'}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {getStatusBadge()}
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <AlertTriangle className="text-muted-foreground h-4 w-4" />
                            </TooltipTrigger>
                            <TooltipContent>{getTooltipContent()}</TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </div>
        );
    }

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <div className={cn('flex items-center gap-2', className)}>
                        {getStatusIcon()}
                        {getStatusBadge()}
                    </div>
                </TooltipTrigger>
                <TooltipContent>{getTooltipContent()}</TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

/**
 * Component that shows all permissions for the current user
 */
export function UserPermissionsSummary() {
    const { userPermissions, userRoles, loading, user } = useRBAC();

    if (!user || loading) {
        return (
            <div className="space-y-2">
                <div className="flex items-center gap-2">
                    <Clock className="h-4 w-4 animate-spin" />
                    <span className="text-muted-foreground text-sm">Loading permissions...</span>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div>
                <h4 className="mb-2 font-medium">Assigned Roles</h4>
                <div className="flex flex-wrap gap-2">
                    {userRoles.length > 0 ? (
                        userRoles.map((role) => (
                            <Badge key={role.id} variant="outline" className="gap-1">
                                <Shield className="h-3 w-3" />
                                {role.display_name}
                            </Badge>
                        ))
                    ) : (
                        <span className="text-muted-foreground text-sm">No roles assigned</span>
                    )}
                </div>
            </div>

            <div>
                <h4 className="mb-2 font-medium">Permissions ({userPermissions.length})</h4>
                <div className="max-h-32 overflow-y-auto">
                    <div className="flex flex-wrap gap-1">
                        {userPermissions.length > 0 ? (
                            userPermissions.map((permission) => (
                                <Badge key={permission} variant="secondary" className="text-xs">
                                    {permission}
                                </Badge>
                            ))
                        ) : (
                            <span className="text-muted-foreground text-sm">No permissions assigned</span>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
