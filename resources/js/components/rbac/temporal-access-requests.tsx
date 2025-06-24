import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useRBAC } from '@/contexts/RBACContext';
import { Role, User } from '@/types/rbac';
import { router } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import { AlertTriangle, CheckCircle, Clock, Search, Shield, User as UserIcon, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

export interface TemporalAccessRequest {
    id: number;
    user: User;
    role: Role;
    duration: number;
    duration_unit: string;
    reason: string;
    emergency: boolean;
    status: 'pending' | 'approved' | 'denied';
    requested_at: string;
    requested_by: User;
    reviewed_by?: User;
    reviewed_at?: string;
    review_reason?: string;
}

interface TemporalAccessRequestsProps {
    requests: TemporalAccessRequest[];
    onRequestUpdate?: () => void;
}

export function TemporalAccessRequests({ requests, onRequestUpdate }: TemporalAccessRequestsProps) {
    const { hasPermission } = useRBAC();
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [selectedRequest, setSelectedRequest] = useState<TemporalAccessRequest | null>(null);
    const [showReviewDialog, setShowReviewDialog] = useState(false);
    const [reviewAction, setReviewAction] = useState<'approve' | 'deny'>('approve');
    const [reviewReason, setReviewReason] = useState('');
    const [loading, setLoading] = useState(false);

    const canApprove = hasPermission('roles.approve_temporal');
    const canDeny = hasPermission('roles.deny_temporal');

    // Filter requests
    const filteredRequests = requests.filter((request) => {
        const matchesSearch =
            request.user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            request.user.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
            request.role.display_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            request.reason.toLowerCase().includes(searchTerm.toLowerCase());

        const matchesStatus = statusFilter === 'all' || request.status === statusFilter;

        return matchesSearch && matchesStatus;
    });

    const pendingRequests = filteredRequests.filter((r) => r.status === 'pending');
    const reviewedRequests = filteredRequests.filter((r) => r.status !== 'pending');

    const getStatusBadge = (status: string, emergency: boolean = false) => {
        switch (status) {
            case 'pending':
                return (
                    <Badge variant={emergency ? 'destructive' : 'secondary'} className="gap-1">
                        <Clock className="h-3 w-3" />
                        {emergency ? 'Emergency Pending' : 'Pending'}
                    </Badge>
                );
            case 'approved':
                return (
                    <Badge variant="default" className="gap-1 bg-green-100 text-green-800">
                        <CheckCircle className="h-3 w-3" />
                        Approved
                    </Badge>
                );
            case 'denied':
                return (
                    <Badge variant="destructive" className="gap-1">
                        <X className="h-3 w-3" />
                        Denied
                    </Badge>
                );
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    const getPriorityColor = (emergency: boolean, status: string) => {
        if (status !== 'pending') return '';
        return emergency ? 'border-red-200 bg-red-50' : '';
    };

    const handleReviewRequest = (request: TemporalAccessRequest, action: 'approve' | 'deny') => {
        setSelectedRequest(request);
        setReviewAction(action);
        setReviewReason('');
        setShowReviewDialog(true);
    };

    const submitReview = () => {
        if (!selectedRequest) return;

        setLoading(true);

        const endpoint =
            reviewAction === 'approve'
                ? `/admin/temporal/${selectedRequest.user.id}/approve`
                : `/admin/temporal/${selectedRequest.user.id}/${selectedRequest.role.id}/deny`;

        const data =
            reviewAction === 'approve'
                ? {
                      role_id: selectedRequest.role.id,
                      duration: selectedRequest.duration,
                      duration_unit: selectedRequest.duration_unit,
                      reason: reviewReason || selectedRequest.reason,
                      requested_by: selectedRequest.requested_by.id,
                  }
                : {
                      reason: reviewReason || `Request denied by administrator`,
                  };

        router.post(endpoint, data, {
            onSuccess: () => {
                toast.success(`Request ${reviewAction}d`, {
                    description: `Temporal access request for ${selectedRequest.user.name} has been ${reviewAction}d.`,
                });
                setShowReviewDialog(false);
                setSelectedRequest(null);
                onRequestUpdate?.();
            },
            onError: (errors) => {
                toast.error(`Failed to ${reviewAction} request`, {
                    description: 'Please try again or contact support.',
                });
                console.error('Review error:', errors);
            },
            onFinish: () => {
                setLoading(false);
            },
        });
    };

    const formatDuration = (duration: number, unit: string) => {
        return `${duration} ${unit}${duration !== 1 ? 's' : ''}`;
    };

    if (!canApprove && !canDeny) {
        return (
            <Card>
                <CardContent className="py-8 text-center">
                    <Shield className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                    <h3 className="mb-2 text-lg font-semibold">Access Restricted</h3>
                    <p className="text-muted-foreground">You don't have permission to view or manage temporal access requests.</p>
                </CardContent>
            </Card>
        );
    }

    return (
        <>
            <div className="space-y-6">
                {/* Header and Filters */}
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">Temporal Access Requests</h2>
                        <p className="text-muted-foreground">Review and manage pending temporal access requests</p>
                    </div>
                    <div className="flex items-center gap-4">
                        <div className="relative">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <Input
                                placeholder="Search requests..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="w-64 pl-9"
                            />
                        </div>
                        <Select value={statusFilter} onValueChange={setStatusFilter}>
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="pending">Pending</SelectItem>
                                <SelectItem value="approved">Approved</SelectItem>
                                <SelectItem value="denied">Denied</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center gap-2">
                                <Clock className="h-4 w-4 text-amber-600" />
                                <div className="text-2xl font-bold">{pendingRequests.length}</div>
                            </div>
                            <p className="text-muted-foreground text-xs">Pending Reviews</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center gap-2">
                                <AlertTriangle className="h-4 w-4 text-red-600" />
                                <div className="text-2xl font-bold">{pendingRequests.filter((r) => r.emergency).length}</div>
                            </div>
                            <p className="text-muted-foreground text-xs">Emergency Requests</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center gap-2">
                                <CheckCircle className="h-4 w-4 text-green-600" />
                                <div className="text-2xl font-bold">{requests.filter((r) => r.status === 'approved').length}</div>
                            </div>
                            <p className="text-muted-foreground text-xs">Approved Today</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center gap-2">
                                <X className="h-4 w-4 text-red-600" />
                                <div className="text-2xl font-bold">{requests.filter((r) => r.status === 'denied').length}</div>
                            </div>
                            <p className="text-muted-foreground text-xs">Denied Today</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Pending Requests */}
                {pendingRequests.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Clock className="h-5 w-5 text-amber-600" />
                                Pending Requests ({pendingRequests.length})
                            </CardTitle>
                            <CardDescription>Requests requiring immediate review</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {pendingRequests.map((request) => (
                                <div key={request.id} className={`rounded-lg border p-4 ${getPriorityColor(request.emergency, request.status)}`}>
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1 space-y-2">
                                            <div className="flex items-center gap-3">
                                                <div className="flex items-center gap-2">
                                                    <UserIcon className="text-muted-foreground h-4 w-4" />
                                                    <span className="font-medium">{request.user.name}</span>
                                                    <span className="text-muted-foreground text-sm">({request.user.email})</span>
                                                </div>
                                                {getStatusBadge(request.status, request.emergency)}
                                            </div>

                                            <div className="text-muted-foreground flex items-center gap-4 text-sm">
                                                <span>
                                                    Role: <strong>{request.role.display_name}</strong>
                                                </span>
                                                <span>
                                                    Duration: <strong>{formatDuration(request.duration, request.duration_unit)}</strong>
                                                </span>
                                                <span>
                                                    Requested:{' '}
                                                    <strong>{formatDistanceToNow(new Date(request.requested_at), { addSuffix: true })}</strong>
                                                </span>
                                            </div>

                                            <div className="text-sm">
                                                <span className="font-medium">Reason:</span> {request.reason}
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2">
                                            {canApprove && (
                                                <Button size="sm" onClick={() => handleReviewRequest(request, 'approve')} className="gap-2">
                                                    <CheckCircle className="h-4 w-4" />
                                                    Approve
                                                </Button>
                                            )}
                                            {canDeny && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleReviewRequest(request, 'deny')}
                                                    className="text-destructive hover:text-destructive gap-2"
                                                >
                                                    <X className="h-4 w-4" />
                                                    Deny
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {/* Reviewed Requests */}
                {reviewedRequests.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Reviews ({reviewedRequests.length})</CardTitle>
                            <CardDescription>Previously reviewed requests</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {reviewedRequests.slice(0, 10).map((request) => (
                                <div key={request.id} className="rounded-lg border p-4">
                                    <div className="flex items-center justify-between">
                                        <div className="flex-1 space-y-1">
                                            <div className="flex items-center gap-3">
                                                <span className="font-medium">{request.user.name}</span>
                                                <span className="text-muted-foreground text-sm">→ {request.role.display_name}</span>
                                                {getStatusBadge(request.status)}
                                            </div>
                                            <div className="text-muted-foreground text-sm">
                                                Reviewed by {request.reviewed_by?.name}{' '}
                                                {formatDistanceToNow(new Date(request.reviewed_at!), { addSuffix: true })}
                                            </div>
                                            {request.review_reason && (
                                                <div className="text-sm">
                                                    <span className="font-medium">Review reason:</span> {request.review_reason}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {filteredRequests.length === 0 && (
                    <Card>
                        <CardContent className="py-8 text-center">
                            <Clock className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                            <h3 className="mb-2 text-lg font-semibold">No requests found</h3>
                            <p className="text-muted-foreground">
                                {searchTerm || statusFilter !== 'all'
                                    ? 'No requests match your current filters.'
                                    : 'No temporal access requests to review at this time.'}
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Review Dialog */}
            <Dialog open={showReviewDialog} onOpenChange={setShowReviewDialog}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            {reviewAction === 'approve' ? <CheckCircle className="h-5 w-5 text-green-600" /> : <X className="h-5 w-5 text-red-600" />}
                            {reviewAction === 'approve' ? 'Approve' : 'Deny'} Request
                        </DialogTitle>
                        <DialogDescription>
                            {reviewAction === 'approve'
                                ? 'This will grant the requested temporal access immediately.'
                                : 'This will deny the temporal access request.'}
                        </DialogDescription>
                    </DialogHeader>

                    {selectedRequest && (
                        <div className="space-y-4">
                            <div className="bg-muted/50 rounded-lg border p-3">
                                <div className="space-y-1 text-sm">
                                    <div>
                                        <strong>User:</strong> {selectedRequest.user.name}
                                    </div>
                                    <div>
                                        <strong>Role:</strong> {selectedRequest.role.display_name}
                                    </div>
                                    <div>
                                        <strong>Duration:</strong> {formatDuration(selectedRequest.duration, selectedRequest.duration_unit)}
                                    </div>
                                    <div>
                                        <strong>Reason:</strong> {selectedRequest.reason}
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="review_reason">
                                    {reviewAction === 'approve' ? 'Additional notes (optional)' : 'Reason for denial *'}
                                </Label>
                                <Textarea
                                    id="review_reason"
                                    placeholder={
                                        reviewAction === 'approve'
                                            ? 'Any additional notes about this approval...'
                                            : 'Please provide a reason for denying this request...'
                                    }
                                    value={reviewReason}
                                    onChange={(e) => setReviewReason(e.target.value)}
                                    rows={3}
                                />
                            </div>

                            <div className="flex justify-end gap-3">
                                <Button variant="outline" onClick={() => setShowReviewDialog(false)} disabled={loading}>
                                    Cancel
                                </Button>
                                <Button
                                    onClick={submitReview}
                                    disabled={loading || (reviewAction === 'deny' && !reviewReason.trim())}
                                    className={reviewAction === 'deny' ? 'bg-red-600 hover:bg-red-700' : ''}
                                >
                                    {loading ? 'Processing...' : reviewAction === 'approve' ? 'Approve Request' : 'Deny Request'}
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
