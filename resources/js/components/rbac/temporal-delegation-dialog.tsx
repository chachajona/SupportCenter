import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { CalendarDays, Clock, User } from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';

interface TemporalDelegationDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: {
        id: number;
        name: string;
        email: string;
    };
    roles: Array<{
        id: number;
        name: string;
        display_name: string;
        description: string;
    }>;
}

export function TemporalDelegationDialog({ open, onOpenChange, user, roles }: TemporalDelegationDialogProps) {
    const [selectedRole, setSelectedRole] = useState<string>('');
    const [duration, setDuration] = useState<string>('60'); // minutes
    const [reason, setReason] = useState<string>('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!selectedRole || !duration || !reason.trim()) {
            toast.error('Please fill in all required fields');
            return;
        }

        setLoading(true);

        try {
            router.post(
                `/admin/users/${user.id}/temporal-access`,
                {
                    role_id: parseInt(selectedRole),
                    duration_minutes: parseInt(duration),
                    reason: reason.trim(),
                },
                {
                    onSuccess: () => {
                        toast.success(`Temporal access granted to ${user.name}`, {
                            description: `Role access will expire in ${duration} minutes`,
                        });
                        onOpenChange(false);
                        resetForm();
                    },
                    onError: (errors) => {
                        const firstError = Object.values(errors)[0];
                        toast.error('Failed to grant access', {
                            description: Array.isArray(firstError) ? firstError[0] : firstError,
                        });
                    },
                    onFinish: () => setLoading(false),
                },
            );
        } catch (error) {
            toast.error('Network error occurred');
            setLoading(false);
            console.error(error);
        }
    };

    const resetForm = () => {
        setSelectedRole('');
        setDuration('60');
        setReason('');
    };

    const selectedRoleData = roles.find((role) => role.id.toString() === selectedRole);
    const expiresAt = new Date(Date.now() + parseInt(duration) * 60000);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Clock className="h-5 w-5 text-blue-600" />
                        Grant Temporal Access
                    </DialogTitle>
                    <DialogDescription>
                        Grant temporary role access to <strong>{user.name}</strong> ({user.email})
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <Label htmlFor="role">Role to Grant</Label>
                        <Select value={selectedRole} onValueChange={setSelectedRole}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select a role..." />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem key={role.id} value={role.id.toString()}>
                                        <div className="flex flex-col gap-1">
                                            <div className="font-medium">{role.display_name}</div>
                                            <div className="text-sm text-gray-500">{role.description}</div>
                                        </div>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div>
                        <Label htmlFor="duration">Duration (minutes)</Label>
                        <Select value={duration} onValueChange={setDuration}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="30">30 minutes</SelectItem>
                                <SelectItem value="60">1 hour</SelectItem>
                                <SelectItem value="120">2 hours</SelectItem>
                                <SelectItem value="240">4 hours</SelectItem>
                                <SelectItem value="480">8 hours</SelectItem>
                                <SelectItem value="1440">24 hours</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div>
                        <Label htmlFor="reason">Reason for Access *</Label>
                        <Textarea
                            id="reason"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            placeholder="Explain why temporal access is needed..."
                            rows={3}
                            required
                        />
                    </div>

                    {selectedRoleData && (
                        <div className="rounded-lg bg-blue-50 p-3">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <User className="h-4 w-4 text-blue-600" />
                                    <span className="font-medium text-blue-900">Access Summary</span>
                                </div>
                                <Badge variant="secondary">Temporary</Badge>
                            </div>
                            <div className="mt-2 space-y-1 text-sm text-blue-800">
                                <div>
                                    <strong>Role:</strong> {selectedRoleData.display_name}
                                </div>
                                <div className="flex items-center gap-1">
                                    <CalendarDays className="h-3 w-3" />
                                    <strong>Expires:</strong> {expiresAt.toLocaleString()}
                                </div>
                            </div>
                        </div>
                    )}
                </form>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>
                        Cancel
                    </Button>
                    <Button type="submit" onClick={handleSubmit} disabled={loading || !selectedRole || !reason.trim()}>
                        {loading ? 'Granting Access...' : 'Grant Access'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
