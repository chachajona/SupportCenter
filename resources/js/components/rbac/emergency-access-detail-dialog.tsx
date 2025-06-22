import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Progress } from '@/components/ui/progress';
import { EmergencyAccess } from '@/types/rbac';
import { AlertTriangle, CheckCircle, Clock, Shield, User, XCircle } from 'lucide-react';

interface EmergencyAccessDetailsDialogProps {
    emergencyAccess: EmergencyAccess | null;
    open: boolean;
    onClose: () => void;
}

export function EmergencyAccessDetailsDialog({ emergencyAccess, open, onClose }: EmergencyAccessDetailsDialogProps) {
    if (!emergencyAccess) return null;

    const getStatusIcon = () => {
        if (!emergencyAccess.is_active) {
            return <XCircle className="h-5 w-5 text-red-500" />;
        }
        if (emergencyAccess.used_at) {
            return <CheckCircle className="h-5 w-5 text-blue-500" />;
        }
        if (new Date(emergencyAccess.expires_at) <= new Date()) {
            return <Clock className="h-5 w-5 text-amber-500" />;
        }
        return <CheckCircle className="h-5 w-5 text-green-500" />;
    };

    const getStatusText = () => {
        if (!emergencyAccess.is_active) return 'Revoked';
        if (emergencyAccess.used_at) return 'Used';
        if (new Date(emergencyAccess.expires_at) <= new Date()) return 'Expired';
        return 'Active';
    };

    const getTimeRemaining = () => {
        const now = new Date();
        const expires = new Date(emergencyAccess.expires_at);
        const diff = expires.getTime() - now.getTime();

        if (diff <= 0) return 'Expired';

        const minutes = Math.floor(diff / (1000 * 60));
        const hours = Math.floor(minutes / 60);

        if (hours > 0) {
            return `${hours}h ${minutes % 60}m remaining`;
        }
        return `${minutes}m remaining`;
    };

    const getProgressPercentage = () => {
        const granted = new Date(emergencyAccess.granted_at).getTime();
        const expires = new Date(emergencyAccess.expires_at).getTime();
        const now = new Date().getTime();

        const total = expires - granted;
        const elapsed = now - granted;

        return Math.min(100, Math.max(0, (elapsed / total) * 100));
    };

    const formatDuration = () => {
        const granted = new Date(emergencyAccess.granted_at);
        const expires = new Date(emergencyAccess.expires_at);
        const duration = expires.getTime() - granted.getTime();
        const minutes = Math.floor(duration / (1000 * 60));

        if (minutes >= 60) {
            const hours = Math.floor(minutes / 60);
            const remainingMinutes = minutes % 60;
            return `${hours}h ${remainingMinutes}m`;
        }
        return `${minutes}m`;
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {getStatusIcon()}
                        Emergency Access Details
                    </DialogTitle>
                    <DialogDescription>Detailed information about emergency access grant #{emergencyAccess.id}</DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Status Overview */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Access Status</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <span className="font-medium">Current Status</span>
                                <Badge
                                    variant={emergencyAccess.is_active && new Date(emergencyAccess.expires_at) > new Date() ? 'default' : 'secondary'}
                                >
                                    {getStatusText()}
                                </Badge>
                            </div>

                            {emergencyAccess.is_active && new Date(emergencyAccess.expires_at) > new Date() && (
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between text-sm">
                                        <span>Time Remaining</span>
                                        <span className="font-medium">{getTimeRemaining()}</span>
                                    </div>
                                    <Progress value={getProgressPercentage()} className="h-2" />
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span className="text-muted-foreground">Granted At</span>
                                    <p className="font-medium">{new Date(emergencyAccess.granted_at).toLocaleString()}</p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Expires At</span>
                                    <p className="font-medium">{new Date(emergencyAccess.expires_at).toLocaleString()}</p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Duration</span>
                                    <p className="font-medium">{formatDuration()}</p>
                                </div>
                                {emergencyAccess.used_at && (
                                    <div>
                                        <span className="text-muted-foreground">First Used</span>
                                        <p className="font-medium">{new Date(emergencyAccess.used_at).toLocaleString()}</p>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* User Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <User className="h-5 w-5" />
                                User Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span className="text-muted-foreground">Granted To</span>
                                    <p className="font-medium">{emergencyAccess.user?.name || 'Unknown User'}</p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Email</span>
                                    <p className="font-medium">{emergencyAccess.user?.email || 'N/A'}</p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Granted By</span>
                                    <p className="font-medium">{emergencyAccess.granted_by_user?.name || 'Unknown'}</p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Department</span>
                                    <p className="font-medium">{emergencyAccess.user?.department?.name || 'N/A'}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Permissions */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <Shield className="h-5 w-5" />
                                Granted Permissions
                            </CardTitle>
                            <CardDescription>{emergencyAccess.permissions.length} permissions granted</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-2">
                                {emergencyAccess.permissions.map((permission, index) => (
                                    <div key={index} className="flex items-center justify-between rounded-lg border p-3">
                                        <div className="flex items-center gap-2">
                                            <Shield className="text-muted-foreground h-4 w-4" />
                                            <span className="font-mono text-sm">{permission}</span>
                                        </div>
                                        <Badge variant="outline" className="text-xs">
                                            {permission.includes('delete') || permission.includes('modify') || permission.includes('system')
                                                ? 'HIGH RISK'
                                                : 'MEDIUM RISK'}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Reason and Justification */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <AlertTriangle className="h-5 w-5" />
                                Justification
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                <div>
                                    <span className="text-muted-foreground text-sm">Reason</span>
                                    <p className="bg-muted mt-1 rounded-lg p-3 text-sm">{emergencyAccess.reason}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Security Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Security Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Access ID</span>
                                    <span className="font-mono">{emergencyAccess.id}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Security Level</span>
                                    <Badge variant="destructive">EMERGENCY</Badge>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Audit Trail</span>
                                    <Badge variant="outline">LOGGED</Badge>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Monitoring</span>
                                    <Badge variant="outline">ACTIVE</Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Actions Timeline */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Timeline</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                <div className="flex items-center gap-3 text-sm">
                                    <div className="h-2 w-2 rounded-full bg-green-500" />
                                    <div className="flex-1">
                                        <span className="font-medium">Access Granted</span>
                                        <p className="text-muted-foreground">{new Date(emergencyAccess.granted_at).toLocaleString()}</p>
                                    </div>
                                </div>

                                {emergencyAccess.used_at && (
                                    <div className="flex items-center gap-3 text-sm">
                                        <div className="h-2 w-2 rounded-full bg-blue-500" />
                                        <div className="flex-1">
                                            <span className="font-medium">First Access</span>
                                            <p className="text-muted-foreground">{new Date(emergencyAccess.used_at).toLocaleString()}</p>
                                        </div>
                                    </div>
                                )}

                                <div className="flex items-center gap-3 text-sm">
                                    <div
                                        className={`h-2 w-2 rounded-full ${
                                            new Date(emergencyAccess.expires_at) <= new Date() ? 'bg-gray-500' : 'bg-amber-500'
                                        }`}
                                    />
                                    <div className="flex-1">
                                        <span className="font-medium">
                                            {new Date(emergencyAccess.expires_at) <= new Date() ? 'Expired' : 'Expires'}
                                        </span>
                                        <p className="text-muted-foreground">{new Date(emergencyAccess.expires_at).toLocaleString()}</p>
                                    </div>
                                </div>

                                {!emergencyAccess.is_active && (
                                    <div className="flex items-center gap-3 text-sm">
                                        <div className="h-2 w-2 rounded-full bg-red-500" />
                                        <div className="flex-1">
                                            <span className="font-medium">Access Revoked</span>
                                            <p className="text-muted-foreground">Manually revoked by administrator</p>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </DialogContent>
        </Dialog>
    );
}
