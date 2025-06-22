import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { PermissionAudit } from '@/types/rbac';
import { X } from 'lucide-react';

interface AuditDetailDialogProps {
    audit: PermissionAudit | null;
    open: boolean;
    onClose: () => void;
}

export function AuditDetailDialog({ audit, open, onClose }: AuditDetailDialogProps) {
    if (!audit) return null;

    const getActionBadgeVariant = (action: string) => {
        switch (action) {
            case 'granted':
                return 'default' as const;
            case 'revoked':
                return 'destructive' as const;
            case 'modified':
                return 'secondary' as const;
            default:
                return 'outline' as const;
        }
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center justify-between">
                        <span>Audit Event Details</span>
                        <Button variant="ghost" size="sm" onClick={onClose}>
                            <X className="h-4 w-4" />
                        </Button>
                    </DialogTitle>
                    <DialogDescription>Detailed information about this audit event</DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Event Summary */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-semibold">Event Summary</h3>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Action</label>
                                <div className="mt-1">
                                    <Badge variant={getActionBadgeVariant(audit.action)}>{audit.action.toUpperCase()}</Badge>
                                </div>
                            </div>
                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Date & Time</label>
                                <div className="mt-1">{new Date(audit.created_at).toLocaleString()}</div>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* User Information */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-semibold">User Information</h3>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Target User</label>
                                <div className="mt-1">{audit.user?.name || 'Unknown User'}</div>
                            </div>
                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Performed By</label>
                                <div className="mt-1">{audit.performed_by_user?.name || 'System'}</div>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Permission/Role Details */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-semibold">{audit.permission ? 'Permission Details' : 'Role Details'}</h3>
                        {audit.permission && (
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">Permission Name</label>
                                    <div className="mt-1">{audit.permission.display_name}</div>
                                </div>
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">Resource</label>
                                    <div className="mt-1">{audit.permission.resource}</div>
                                </div>
                            </div>
                        )}
                        {audit.role && (
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">Role Name</label>
                                    <div className="mt-1">{audit.role.display_name}</div>
                                </div>
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">Description</label>
                                    <div className="mt-1">{audit.role.description}</div>
                                </div>
                            </div>
                        )}
                    </div>

                    {audit.reason && (
                        <>
                            <Separator />
                            <div className="space-y-4">
                                <h3 className="text-lg font-semibold">Reason</h3>
                                <div className="bg-muted rounded-lg p-3 text-sm">{audit.reason}</div>
                            </div>
                        </>
                    )}

                    {/* Technical Details */}
                    {(audit.ip_address || audit.user_agent) && (
                        <>
                            <Separator />
                            <div className="space-y-4">
                                <h3 className="text-lg font-semibold">Technical Details</h3>
                                <div className="grid gap-4">
                                    {audit.ip_address && (
                                        <div>
                                            <label className="text-muted-foreground text-sm font-medium">IP Address</label>
                                            <div className="mt-1 font-mono text-sm">{audit.ip_address}</div>
                                        </div>
                                    )}
                                    {audit.user_agent && (
                                        <div>
                                            <label className="text-muted-foreground text-sm font-medium">User Agent</label>
                                            <div className="mt-1 text-sm">{audit.user_agent}</div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </>
                    )}

                    {/* Changes Details */}
                    {(audit.old_values || audit.new_values) && (
                        <>
                            <Separator />
                            <div className="space-y-4">
                                <h3 className="text-lg font-semibold">Changes</h3>
                                <div className="grid gap-4">
                                    {audit.old_values && (
                                        <div>
                                            <label className="text-muted-foreground text-sm font-medium">Old Values</label>
                                            <pre className="bg-muted mt-1 rounded-lg p-3 text-xs">{JSON.stringify(audit.old_values, null, 2)}</pre>
                                        </div>
                                    )}
                                    {audit.new_values && (
                                        <div>
                                            <label className="text-muted-foreground text-sm font-medium">New Values</label>
                                            <pre className="bg-muted mt-1 rounded-lg p-3 text-xs">{JSON.stringify(audit.new_values, null, 2)}</pre>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
